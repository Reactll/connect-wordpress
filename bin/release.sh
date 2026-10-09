#!/usr/bin/env bash
# Build (and with --publish, upload) a signed Reactll Connect release.
set -euo pipefail
cd "$(dirname "$0")/.."

KEY="${REACTLL_CONNECT_SIGNING_KEY_FILE:-$HOME/.reactll-connect/signing.key}"
REMOTE="runcloud@104.248.143.61:/home/runcloud/webapps/reactll/storage/app/connect/wordpress/"

VERSION=$(grep -E '^ \* Version:' reactll-connect/reactll-connect.php | awk '{print $3}')
DEFINED=$(grep -E "define\('REACTLL_CONNECT_VERSION'" reactll-connect/reactll-connect.php | sed -E "s/.*'([0-9.]+)'.*/\1/")
STABLE=$(grep -E '^Stable tag:' reactll-connect/readme.txt | awk '{print $3}')
[[ "$VERSION" == "$DEFINED" && "$VERSION" == "$STABLE" ]] || { echo "Version mismatch: header $VERSION, constant $DEFINED, readme $STABLE"; exit 1; }
[[ -f "$KEY" ]] || { echo "Signing key not found at $KEY"; exit 1; }

for f in reactll-connect/*.php reactll-connect/includes/*.php; do php -l "$f" >/dev/null; done

FILE="reactll-connect-$VERSION.zip"
mkdir -p dist
rm -f "dist/$FILE" "dist/$FILE.sig"
zip -rq "dist/$FILE" reactll-connect -x '*.DS_Store'

php -r '
    $sk = base64_decode(trim(file_get_contents($argv[1])), true);
    echo base64_encode(sodium_crypto_sign_detached(file_get_contents($argv[2]), $sk));
' "$KEY" "dist/$FILE" > "dist/$FILE.sig"

CHANGELOG=$(awk '/^= '"$VERSION"' =/{flag=1;next}/^= /{flag=0}flag' reactll-connect/readme.txt | sed '/^$/d')
php -r '
    echo json_encode([
        "version" => $argv[1], "file" => $argv[2], "requires" => "5.8", "tested" => $argv[3], "requires_php" => "7.4",
        "changelog" => "<ul>".implode("", array_map(fn ($l) => "<li>".htmlspecialchars(ltrim($l, "* "))."</li>", array_filter(explode("\n", $argv[4])))). "</ul>",
        "released_at" => gmdate("c"),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
' "$VERSION" "$FILE" "$(grep -E '^Tested up to:' reactll-connect/readme.txt | awk '{print $4}')" "$CHANGELOG" > dist/latest.json

echo "Built dist/$FILE (+ .sig, latest.json)"

if [[ "${1:-}" == "--publish" ]]; then
    ssh runcloud@104.248.143.61 "mkdir -p /home/runcloud/webapps/reactll/storage/app/connect/wordpress"
    scp -q "dist/$FILE" "dist/$FILE.sig" "$REMOTE"
    scp -q dist/latest.json "$REMOTE"
    echo "Published $VERSION"
fi
