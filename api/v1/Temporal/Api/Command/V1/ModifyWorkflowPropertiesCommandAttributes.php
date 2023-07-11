<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: temporal/api/command/v1/message.proto

namespace Temporal\Api\Command\V1;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>temporal.api.command.v1.ModifyWorkflowPropertiesCommandAttributes</code>
 */
class ModifyWorkflowPropertiesCommandAttributes extends \Google\Protobuf\Internal\Message
{
    /**
     * If set, update the workflow memo with the provided values. The values will be merged with
     * the existing memo. If the user wants to delete values, a default/empty Payload should be
     * used as the value for the key being deleted.
     *
     * Generated from protobuf field <code>.temporal.api.common.v1.Memo upserted_memo = 1;</code>
     */
    protected $upserted_memo = null;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type \Temporal\Api\Common\V1\Memo $upserted_memo
     *           If set, update the workflow memo with the provided values. The values will be merged with
     *           the existing memo. If the user wants to delete values, a default/empty Payload should be
     *           used as the value for the key being deleted.
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Temporal\Api\Command\V1\Message::initOnce();
        parent::__construct($data);
    }

    /**
     * If set, update the workflow memo with the provided values. The values will be merged with
     * the existing memo. If the user wants to delete values, a default/empty Payload should be
     * used as the value for the key being deleted.
     *
     * Generated from protobuf field <code>.temporal.api.common.v1.Memo upserted_memo = 1;</code>
     * @return \Temporal\Api\Common\V1\Memo|null
     */
    public function getUpsertedMemo()
    {
        return $this->upserted_memo;
    }

    public function hasUpsertedMemo()
    {
        return isset($this->upserted_memo);
    }

    public function clearUpsertedMemo()
    {
        unset($this->upserted_memo);
    }

    /**
     * If set, update the workflow memo with the provided values. The values will be merged with
     * the existing memo. If the user wants to delete values, a default/empty Payload should be
     * used as the value for the key being deleted.
     *
     * Generated from protobuf field <code>.temporal.api.common.v1.Memo upserted_memo = 1;</code>
     * @param \Temporal\Api\Common\V1\Memo $var
     * @return $this
     */
    public function setUpsertedMemo($var)
    {
        GPBUtil::checkMessage($var, \Temporal\Api\Common\V1\Memo::class);
        $this->upserted_memo = $var;

        return $this;
    }

}

