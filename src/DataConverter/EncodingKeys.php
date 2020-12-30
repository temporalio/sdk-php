<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Client\DataConverter;

final class EncodingKeys
{
    const METADATA_ENCODING_KEY = "encoding";

    const METADATA_ENCODING_NULL = "binary/null";
    const METADATA_ENCODING_RAW = "binary/plain";
    const METADATA_ENCODING_JSON = "json/plain";

    // todo: think about it
    const METADATA_ENCODING_PROTOBUF_JSON = "json/protobuf";
    const METADATA_ENCODING_PROTOBUF = "binary/protobuf";
}
