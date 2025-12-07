#!/bin/bash -xe

LICENSE_FILE=sg_api_test.lic

sourceguardian \
        -r \
        src/ \
        -o dst \
        -p @sg_header.php \
        --keep-file-date \
        -n \
        --phpversion 8.2 \
        --phpversion 8.3 \
        --phpversion 8.4 \
        --entangle 5 \
        --projid ab3a3773e8abf9c2d8aaa78ced18baf2a0d6c0fd499abc7bf2e8b7c5006c2712 \
        --projkey 852a68b718dcfa33c76a9051046bdd4cf4956d89ce61d9e7b66a7bd66ae6a73a \
        --external "$LICENSE_FILE" \
        --catch ERR_ALL=sg_catchall \
        --const build_id=developer1 \
        --const license_file="$LICENSE_FILE"

sourceguardian \
        sg_error_page.php \
        -o dst/src \
        --keep-file-date \
        -n \
        --phpversion 8.2 \
        --phpversion 8.3 \
        --phpversion 8.4 \
        --entangle 5 \
        --projid 0caa7405f377fada8ce912f3eeda29904cdc4977a81fffc1605d4c1271b9ecf3 \
        --projkey 6c9955a9fb8a7926db3058b7f70b1745bffa4a01f840b267c20c2f0ab33b0aef \
        --const build_id=developer1 \
        --const license_file="$LICENSE_FILE"
