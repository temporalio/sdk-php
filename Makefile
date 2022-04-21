# default target
default: echo

generate-proto:
	php resources/scripts/generate-proto.php

generate-client:
	php resources/scripts/generate-client.php
