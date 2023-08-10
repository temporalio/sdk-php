<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

final class EncodingKeys
{
    public const METADATA_ENCODING_KEY = 'encoding';
    public const METADATA_MESSAGE_TYPE = 'messageType';

    public const METADATA_ENCODING_NULL = 'binary/null';
    public const METADATA_ENCODING_RAW = 'binary/plain';
    public const METADATA_ENCODING_JSON = 'json/plain';

    public const METADATA_ENCODING_PROTOBUF_JSON = 'json/protobuf';
    public const METADATA_ENCODING_PROTOBUF = 'binary/protobuf';
}
