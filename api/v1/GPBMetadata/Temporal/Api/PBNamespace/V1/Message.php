<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: temporal/api/namespace/v1/message.proto

namespace GPBMetadata\Temporal\Api\PBNamespace\V1;

class Message
{
    public static $is_initialized = false;

    public static function initOnce() {
        $pool = \Google\Protobuf\Internal\DescriptorPool::getGeneratedPool();

        if (static::$is_initialized == true) {
          return;
        }
        \GPBMetadata\Google\Protobuf\Duration::initOnce();
        \GPBMetadata\Google\Protobuf\Timestamp::initOnce();
        \GPBMetadata\Dependencies\Gogoproto\Gogo::initOnce();
        \GPBMetadata\Temporal\Api\Enums\V1\PBNamespace::initOnce();
        $pool->internalAddGeneratedFile(
            '
�
\'temporal/api/namespace/v1/message.prototemporal.api.namespace.v1google/protobuf/timestamp.proto!dependencies/gogoproto/gogo.proto%temporal/api/enums/v1/namespace.proto"�

NamespaceInfo
name (	4
state (2%.temporal.api.enums.v1.NamespaceState
description (	
owner_email (	@
data (22.temporal.api.namespace.v1.NamespaceInfo.DataEntry

id (	
supports_schedulesd (+
	DataEntry
key (	
value (	:8"�
NamespaceConfigI
 workflow_execution_retention_ttl (2.google.protobuf.DurationB��<
bad_binaries (2&.temporal.api.namespace.v1.BadBinariesD
history_archival_state (2$.temporal.api.enums.v1.ArchivalState
history_archival_uri (	G
visibility_archival_state (2$.temporal.api.enums.v1.ArchivalState
visibility_archival_uri (	"�
BadBinariesF
binaries (24.temporal.api.namespace.v1.BadBinaries.BinariesEntryY

BinariesEntry
key (	7
value (2(.temporal.api.namespace.v1.BadBinaryInfo:8"h

BadBinaryInfo
reason (	
operator (	5
create_time (2.google.protobuf.TimestampB��"�
UpdateNamespaceInfo
description (	
owner_email (	F
data (28.temporal.api.namespace.v1.UpdateNamespaceInfo.DataEntry4
state (2%.temporal.api.enums.v1.NamespaceState+
	DataEntry
key (	
value (	:8"*
NamespaceFilter
include_deleted (B�
io.temporal.api.namespace.v1BMessageProtoPZ)go.temporal.io/api/namespace/v1;namespace�Temporal.Api.Namespace.V1�Temporal::Api::Namespace::V1bproto3'
        , true);

        static::$is_initialized = true;
    }
}

