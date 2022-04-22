# default target
default: echo

echo:
	@echo "Hello world!"

generate-proto:
	php resources/scripts/generate-proto.php

generate-client:
	php resources/scripts/generate-client.php
