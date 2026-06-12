#!/usr/bin/env bash
set -euo pipefail

VERSION="${ASCIINEMA_VERSION:-3.2.0}"
ASSET="${ASCIINEMA_ASSET:-asciinema-x86_64-unknown-linux-gnu}"
INSTALL_DIR="${INSTALL_DIR:-/usr/local/bin}"
TARGET_NAME="${TARGET_NAME:-ciine}"
URL="${ASCIINEMA_URL:-https://github.com/asciinema/asciinema/releases/download/v${VERSION}/${ASSET}}"
TARGET="${INSTALL_DIR}/${TARGET_NAME}"
TMP_FILE="$(mktemp)"

cleanup() {
    rm -f "$TMP_FILE"
}
trap cleanup EXIT

download() {
    if command -v curl >/dev/null 2>&1; then
        curl -fsSL "$URL" -o "$TMP_FILE"
        return
    fi

    if command -v wget >/dev/null 2>&1; then
        wget -qO "$TMP_FILE" "$URL"
        return
    fi

    echo "Neither curl nor wget is installed." >&2
    exit 1
}

install_binary() {
    chmod +x "$TMP_FILE"

    if mkdir -p "$INSTALL_DIR" 2>/dev/null && [ -w "$INSTALL_DIR" ]; then
        mv "$TMP_FILE" "$TARGET"
        return
    fi

    if [ "$(id -u)" -eq 0 ]; then
        mkdir -p "$INSTALL_DIR"
        mv "$TMP_FILE" "$TARGET"
        return
    fi

    if ! command -v sudo >/dev/null 2>&1; then
        echo "Installing to ${INSTALL_DIR} requires sudo. Re-run with INSTALL_DIR set to a writable directory." >&2
        exit 1
    fi

    sudo mkdir -p "$INSTALL_DIR"
    sudo install -m 0755 "$TMP_FILE" "$TARGET"
}

config_home() {
    if [ -n "${ASCIINEMA_CONFIG_HOME:-}" ]; then
        printf '%s\n' "$ASCIINEMA_CONFIG_HOME"
    elif [ -n "${XDG_CONFIG_HOME:-}" ]; then
        printf '%s\n' "$XDG_CONFIG_HOME/asciinema"
    else
        printf '%s\n' "$HOME/.config/asciinema"
    fi
}

write_config() {
    local config_dir config_file next_file

    config_dir="$(config_home)"
    config_file="${ASCIINEMA_CONFIG_FILE:-$config_dir/config.toml}"
    next_file="$(mktemp)"

    mkdir -p "$(dirname "$config_file")"

    if [ ! -f "$config_file" ]; then
        cat > "$config_file" <<'TOML'
[session]
capture_input = true
idle_time_limit = 0.2
TOML
        echo "Wrote asciinema config to ${config_file}"
        return
    fi

    awk '
        function write_missing() {
            if (in_session && !wrote_missing) {
                if (!has_capture) print "capture_input = true"
                if (!has_idle) print "idle_time_limit = 0.2"
                wrote_missing = 1
            }
        }

        /^\[[^]]+\][[:space:]]*$/ {
            if (in_session) write_missing()
            in_session = ($0 == "[session]")
            if (in_session) seen_session = 1
            print
            next
        }

        in_session && /^[[:space:]]*capture_input[[:space:]]*=/ {
            print "capture_input = true"
            has_capture = 1
            next
        }

        in_session && /^[[:space:]]*idle_time_limit[[:space:]]*=/ {
            print "idle_time_limit = 0.2"
            has_idle = 1
            next
        }

        { print }

        END {
            if (in_session) write_missing()
            if (!seen_session) {
                print ""
                print "[session]"
                print "capture_input = true"
                print "idle_time_limit = 0.2"
            }
        }
    ' "$config_file" > "$next_file"

    mv "$next_file" "$config_file"
    echo "Updated asciinema config at ${config_file}"
}

download
install_binary
write_config

echo "Installed asciinema ${VERSION} to ${TARGET}"
"$TARGET" --version
