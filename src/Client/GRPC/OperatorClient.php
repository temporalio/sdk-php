<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC;

use Temporal\Api\Operatorservice\V1\AddOrUpdateRemoteClusterRequest;
use Temporal\Api\Operatorservice\V1\AddOrUpdateRemoteClusterResponse;
use Temporal\Api\Operatorservice\V1\AddSearchAttributesRequest;
use Temporal\Api\Operatorservice\V1\AddSearchAttributesResponse;
use Temporal\Api\Operatorservice\V1\CreateNexusEndpointRequest;
use Temporal\Api\Operatorservice\V1\CreateNexusEndpointResponse;
use Temporal\Api\Operatorservice\V1\DeleteNamespaceRequest;
use Temporal\Api\Operatorservice\V1\DeleteNamespaceResponse;
use Temporal\Api\Operatorservice\V1\DeleteNexusEndpointRequest;
use Temporal\Api\Operatorservice\V1\DeleteNexusEndpointResponse;
use Temporal\Api\Operatorservice\V1\GetNexusEndpointRequest;
use Temporal\Api\Operatorservice\V1\GetNexusEndpointResponse;
use Temporal\Api\Operatorservice\V1\ListClustersRequest;
use Temporal\Api\Operatorservice\V1\ListClustersResponse;
use Temporal\Api\Operatorservice\V1\ListNexusEndpointsRequest;
use Temporal\Api\Operatorservice\V1\ListNexusEndpointsResponse;
use Temporal\Api\Operatorservice\V1\ListSearchAttributesRequest;
use Temporal\Api\Operatorservice\V1\ListSearchAttributesResponse;
use Temporal\Api\Operatorservice\V1\RemoveRemoteClusterRequest;
use Temporal\Api\Operatorservice\V1\RemoveRemoteClusterResponse;
use Temporal\Api\Operatorservice\V1\RemoveSearchAttributesRequest;
use Temporal\Api\Operatorservice\V1\RemoveSearchAttributesResponse;
use Temporal\Api\Operatorservice\V1\UpdateNexusEndpointRequest;
use Temporal\Api\Operatorservice\V1\UpdateNexusEndpointResponse;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Api\Operatorservice\V1\OperatorServiceClient;

class OperatorClient extends BaseClient implements OperatorClientInterface
{
    /**
     * AddSearchAttributes add custom search attributes.
     *
     * Returns ALREADY_EXISTS status code if a Search Attribute with any of the
     * specified names already exists
     * Returns INTERNAL status code with
     * temporal.api.errordetails.v1.SystemWorkflowFailure in Error Details if
     * registration process fails,
     *
     * @throws ServiceClientException
     */
    public function AddSearchAttributes(AddSearchAttributesRequest $arg, ?ContextInterface $ctx = null): AddSearchAttributesResponse
    {
        return $this->invoke("AddSearchAttributes", $arg, $ctx);
    }

    /**
     * RemoveSearchAttributes removes custom search attributes.
     *
     * Returns NOT_FOUND status code if a Search Attribute with any of the specified
     * names is not registered
     *
     * @throws ServiceClientException
     */
    public function RemoveSearchAttributes(RemoveSearchAttributesRequest $arg, ?ContextInterface $ctx = null): RemoveSearchAttributesResponse
    {
        return $this->invoke("RemoveSearchAttributes", $arg, $ctx);
    }

    /**
     * ListSearchAttributes returns comprehensive information about search attributes.
     *
     * @throws ServiceClientException
     */
    public function ListSearchAttributes(ListSearchAttributesRequest $arg, ?ContextInterface $ctx = null): ListSearchAttributesResponse
    {
        return $this->invoke("ListSearchAttributes", $arg, $ctx);
    }

    /**
     * DeleteNamespace synchronously deletes a namespace and asynchronously reclaims
     * all namespace resources.
     *
     * @throws ServiceClientException
     */
    public function DeleteNamespace(DeleteNamespaceRequest $arg, ?ContextInterface $ctx = null): DeleteNamespaceResponse
    {
        return $this->invoke("DeleteNamespace", $arg, $ctx);
    }

    /**
     * AddOrUpdateRemoteCluster adds or updates remote cluster.
     *
     * @throws ServiceClientException
     */
    public function AddOrUpdateRemoteCluster(AddOrUpdateRemoteClusterRequest $arg, ?ContextInterface $ctx = null): AddOrUpdateRemoteClusterResponse
    {
        return $this->invoke("AddOrUpdateRemoteCluster", $arg, $ctx);
    }

    /**
     * RemoveRemoteCluster removes remote cluster.
     *
     * @throws ServiceClientException
     */
    public function RemoveRemoteCluster(RemoveRemoteClusterRequest $arg, ?ContextInterface $ctx = null): RemoveRemoteClusterResponse
    {
        return $this->invoke("RemoveRemoteCluster", $arg, $ctx);
    }

    /**
     * ListClusters returns information about Temporal clusters.
     *
     * @throws ServiceClientException
     */
    public function ListClusters(ListClustersRequest $arg, ?ContextInterface $ctx = null): ListClustersResponse
    {
        return $this->invoke("ListClusters", $arg, $ctx);
    }

    /**
     * Get a registered Nexus endpoint by ID. The returned version can be used for
     * optimistic updates.
     *
     * @throws ServiceClientException
     */
    public function GetNexusEndpoint(GetNexusEndpointRequest $arg, ?ContextInterface $ctx = null): GetNexusEndpointResponse
    {
        return $this->invoke("GetNexusEndpoint", $arg, $ctx);
    }

    /**
     * Create a Nexus endpoint. This will fail if an endpoint with the same name is
     * already registered with a status of
     * ALREADY_EXISTS.
     * Returns the created endpoint with its initial version. You may use this version
     * for subsequent updates.
     *
     * @throws ServiceClientException
     */
    public function CreateNexusEndpoint(CreateNexusEndpointRequest $arg, ?ContextInterface $ctx = null): CreateNexusEndpointResponse
    {
        return $this->invoke("CreateNexusEndpoint", $arg, $ctx);
    }

    /**
     * Optimistically update a Nexus endpoint based on provided version as obtained via
     * the `GetNexusEndpoint` or
     * `ListNexusEndpointResponse` APIs. This will fail with a status of
     * FAILED_PRECONDITION if the version does not
     * match.
     * Returns the updated endpoint with its updated version. You may use this version
     * for subsequent updates. You don't
     * need to increment the version yourself. The server will increment the version
     * for you after each update.
     *
     * @throws ServiceClientException
     */
    public function UpdateNexusEndpoint(UpdateNexusEndpointRequest $arg, ?ContextInterface $ctx = null): UpdateNexusEndpointResponse
    {
        return $this->invoke("UpdateNexusEndpoint", $arg, $ctx);
    }

    /**
     * Delete an incoming Nexus service by ID.
     *
     * @throws ServiceClientException
     */
    public function DeleteNexusEndpoint(DeleteNexusEndpointRequest $arg, ?ContextInterface $ctx = null): DeleteNexusEndpointResponse
    {
        return $this->invoke("DeleteNexusEndpoint", $arg, $ctx);
    }

    /**
     * List all Nexus endpoints for the cluster, sorted by ID in ascending order. Set
     * page_token in the request to the
     * next_page_token field of the previous response to get the next page of results.
     * An empty next_page_token
     * indicates that there are no more results. During pagination, a newly added
     * service with an ID lexicographically
     * earlier than the previous page's last endpoint's ID may be missed.
     *
     * @throws ServiceClientException
     */
    public function ListNexusEndpoints(ListNexusEndpointsRequest $arg, ?ContextInterface $ctx = null): ListNexusEndpointsResponse
    {
        return $this->invoke("ListNexusEndpoints", $arg, $ctx);
    }

    protected static function createGrpcStub(string $address, array $options): \Grpc\BaseStub
    {
        return new OperatorServiceClient($address, $options);
    }
}
