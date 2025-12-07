#!/bin/bash -xe

licgen \
	--projid ab3a3773e8abf9c2d8aaa78ced18baf2a0d6c0fd499abc7bf2e8b7c5006c2712 \
        --projkey 852a68b718dcfa33c76a9051046bdd4cf4956d89ce61d9e7b66a7bd66ae6a73a \
	--machine-id 947AAB33C833D536AD9376DD2501D6B4 \
	--expire 3d \
	--text "3 Day Trial License" \
	--const "edition=3 Day Trial" \
	sg_api_test.lic
