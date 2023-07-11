<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: temporal/api/operatorservice/v1/request_response.proto

namespace Temporal\Api\Operatorservice\V1;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>temporal.api.operatorservice.v1.DeleteNamespaceResponse</code>
 */
class DeleteNamespaceResponse extends \Google\Protobuf\Internal\Message
{
    /**
     * Temporary namespace name that is used during reclaim resources step.
     *
     * Generated from protobuf field <code>string deleted_namespace = 1;</code>
     */
    protected $deleted_namespace = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $deleted_namespace
     *           Temporary namespace name that is used during reclaim resources step.
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Temporal\Api\Operatorservice\V1\RequestResponse::initOnce();
        parent::__construct($data);
    }

    /**
     * Temporary namespace name that is used during reclaim resources step.
     *
     * Generated from protobuf field <code>string deleted_namespace = 1;</code>
     * @return string
     */
    public function getDeletedNamespace()
    {
        return $this->deleted_namespace;
    }

    /**
     * Temporary namespace name that is used during reclaim resources step.
     *
     * Generated from protobuf field <code>string deleted_namespace = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setDeletedNamespace($var)
    {
        GPBUtil::checkString($var, True);
        $this->deleted_namespace = $var;

        return $this;
    }

}

