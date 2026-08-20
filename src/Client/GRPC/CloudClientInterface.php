<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC;

use Temporal\Api\Cloud\Cloudservice\V1;
use Temporal\Exception\Client\ServiceClientException;

interface CloudClientInterface extends GrpcClientInterface
{
    /**
     * Pin the Cloud Operations API version.
     *
     * Sets the `temporal-cloud-api-version` header for every call made by the returned
     * client.
     *
     * @link https://docs.temporal.io/ops
     */
    public function withApiVersion(string $version): static;

    /**
     * Get information about the current authenticated user or service account
     * principal
     *
     * @throws ServiceClientException
     */
    public function GetCurrentIdentity(V1\GetCurrentIdentityRequest $arg, ?ContextInterface $ctx = null): V1\GetCurrentIdentityResponse;

    /**
     * Gets all known users
     *
     * @throws ServiceClientException
     */
    public function GetUsers(V1\GetUsersRequest $arg, ?ContextInterface $ctx = null): V1\GetUsersResponse;

    /**
     * Get a user
     *
     * @throws ServiceClientException
     */
    public function GetUser(V1\GetUserRequest $arg, ?ContextInterface $ctx = null): V1\GetUserResponse;

    /**
     * Create a user
     *
     * @throws ServiceClientException
     */
    public function CreateUser(V1\CreateUserRequest $arg, ?ContextInterface $ctx = null): V1\CreateUserResponse;

    /**
     * Update a user
     *
     * @throws ServiceClientException
     */
    public function UpdateUser(V1\UpdateUserRequest $arg, ?ContextInterface $ctx = null): V1\UpdateUserResponse;

    /**
     * Delete a user
     *
     * @throws ServiceClientException
     */
    public function DeleteUser(V1\DeleteUserRequest $arg, ?ContextInterface $ctx = null): V1\DeleteUserResponse;

    /**
     * Set a user's access to a namespace
     *
     * @throws ServiceClientException
     */
    public function SetUserNamespaceAccess(V1\SetUserNamespaceAccessRequest $arg, ?ContextInterface $ctx = null): V1\SetUserNamespaceAccessResponse;

    /**
     * Get the latest information on an async operation
     *
     * @throws ServiceClientException
     */
    public function GetAsyncOperation(V1\GetAsyncOperationRequest $arg, ?ContextInterface $ctx = null): V1\GetAsyncOperationResponse;

    /**
     * Create a new namespace
     *
     * @throws ServiceClientException
     */
    public function CreateNamespace(V1\CreateNamespaceRequest $arg, ?ContextInterface $ctx = null): V1\CreateNamespaceResponse;

    /**
     * Get all namespaces
     *
     * @throws ServiceClientException
     */
    public function GetNamespaces(V1\GetNamespacesRequest $arg, ?ContextInterface $ctx = null): V1\GetNamespacesResponse;

    /**
     * Get a namespace
     *
     * @throws ServiceClientException
     */
    public function GetNamespace(V1\GetNamespaceRequest $arg, ?ContextInterface $ctx = null): V1\GetNamespaceResponse;

    /**
     * Update a namespace
     *
     * @throws ServiceClientException
     */
    public function UpdateNamespace(V1\UpdateNamespaceRequest $arg, ?ContextInterface $ctx = null): V1\UpdateNamespaceResponse;

    /**
     * Rename an existing customer search attribute
     *
     * @throws ServiceClientException
     */
    public function RenameCustomSearchAttribute(V1\RenameCustomSearchAttributeRequest $arg, ?ContextInterface $ctx = null): V1\RenameCustomSearchAttributeResponse;

    /**
     * Delete a namespace
     *
     * @throws ServiceClientException
     */
    public function DeleteNamespace(V1\DeleteNamespaceRequest $arg, ?ContextInterface $ctx = null): V1\DeleteNamespaceResponse;

    /**
     * Failover a multi-region namespace
     *
     * @throws ServiceClientException
     */
    public function FailoverNamespaceRegion(V1\FailoverNamespaceRegionRequest $arg, ?ContextInterface $ctx = null): V1\FailoverNamespaceRegionResponse;

    /**
     * @throws ServiceClientException
     */
    public function AddNamespaceRegion(V1\AddNamespaceRegionRequest $arg, ?ContextInterface $ctx = null): V1\AddNamespaceRegionResponse;

    /**
     * @throws ServiceClientException
     */
    public function DeleteNamespaceRegion(V1\DeleteNamespaceRegionRequest $arg, ?ContextInterface $ctx = null): V1\DeleteNamespaceRegionResponse;

    /**
     * Get all regions
     *
     * @throws ServiceClientException
     */
    public function GetRegions(V1\GetRegionsRequest $arg, ?ContextInterface $ctx = null): V1\GetRegionsResponse;

    /**
     * Get a region
     *
     * @throws ServiceClientException
     */
    public function GetRegion(V1\GetRegionRequest $arg, ?ContextInterface $ctx = null): V1\GetRegionResponse;

    /**
     * Get all known API keys
     *
     * @throws ServiceClientException
     */
    public function GetApiKeys(V1\GetApiKeysRequest $arg, ?ContextInterface $ctx = null): V1\GetApiKeysResponse;

    /**
     * Get an API key
     *
     * @throws ServiceClientException
     */
    public function GetApiKey(V1\GetApiKeyRequest $arg, ?ContextInterface $ctx = null): V1\GetApiKeyResponse;

    /**
     * Create an API key
     *
     * @throws ServiceClientException
     */
    public function CreateApiKey(V1\CreateApiKeyRequest $arg, ?ContextInterface $ctx = null): V1\CreateApiKeyResponse;

    /**
     * Update an API key
     *
     * @throws ServiceClientException
     */
    public function UpdateApiKey(V1\UpdateApiKeyRequest $arg, ?ContextInterface $ctx = null): V1\UpdateApiKeyResponse;

    /**
     * Delete an API key
     *
     * @throws ServiceClientException
     */
    public function DeleteApiKey(V1\DeleteApiKeyRequest $arg, ?ContextInterface $ctx = null): V1\DeleteApiKeyResponse;

    /**
     * Gets nexus endpoints
     *
     * @throws ServiceClientException
     */
    public function GetNexusEndpoints(V1\GetNexusEndpointsRequest $arg, ?ContextInterface $ctx = null): V1\GetNexusEndpointsResponse;

    /**
     * Get a nexus endpoint
     *
     * @throws ServiceClientException
     */
    public function GetNexusEndpoint(V1\GetNexusEndpointRequest $arg, ?ContextInterface $ctx = null): V1\GetNexusEndpointResponse;

    /**
     * Create a nexus endpoint
     *
     * @throws ServiceClientException
     */
    public function CreateNexusEndpoint(V1\CreateNexusEndpointRequest $arg, ?ContextInterface $ctx = null): V1\CreateNexusEndpointResponse;

    /**
     * Update a nexus endpoint
     *
     * @throws ServiceClientException
     */
    public function UpdateNexusEndpoint(V1\UpdateNexusEndpointRequest $arg, ?ContextInterface $ctx = null): V1\UpdateNexusEndpointResponse;

    /**
     * Delete a nexus endpoint
     *
     * @throws ServiceClientException
     */
    public function DeleteNexusEndpoint(V1\DeleteNexusEndpointRequest $arg, ?ContextInterface $ctx = null): V1\DeleteNexusEndpointResponse;

    /**
     * Get all user groups
     *
     * @throws ServiceClientException
     */
    public function GetUserGroups(V1\GetUserGroupsRequest $arg, ?ContextInterface $ctx = null): V1\GetUserGroupsResponse;

    /**
     * Get a user group
     *
     * @throws ServiceClientException
     */
    public function GetUserGroup(V1\GetUserGroupRequest $arg, ?ContextInterface $ctx = null): V1\GetUserGroupResponse;

    /**
     * Create new a user group
     *
     * @throws ServiceClientException
     */
    public function CreateUserGroup(V1\CreateUserGroupRequest $arg, ?ContextInterface $ctx = null): V1\CreateUserGroupResponse;

    /**
     * Update a user group
     *
     * @throws ServiceClientException
     */
    public function UpdateUserGroup(V1\UpdateUserGroupRequest $arg, ?ContextInterface $ctx = null): V1\UpdateUserGroupResponse;

    /**
     * Delete a user group
     *
     * @throws ServiceClientException
     */
    public function DeleteUserGroup(V1\DeleteUserGroupRequest $arg, ?ContextInterface $ctx = null): V1\DeleteUserGroupResponse;

    /**
     * Set a user group's access to a namespace
     *
     * @throws ServiceClientException
     */
    public function SetUserGroupNamespaceAccess(V1\SetUserGroupNamespaceAccessRequest $arg, ?ContextInterface $ctx = null): V1\SetUserGroupNamespaceAccessResponse;

    /**
     * Add a member to the group, can only be used with Cloud group types.
     *
     * @throws ServiceClientException
     */
    public function AddUserGroupMember(V1\AddUserGroupMemberRequest $arg, ?ContextInterface $ctx = null): V1\AddUserGroupMemberResponse;

    /**
     * Remove a member from the group, can only be used with Cloud group types.
     *
     * @throws ServiceClientException
     */
    public function RemoveUserGroupMember(V1\RemoveUserGroupMemberRequest $arg, ?ContextInterface $ctx = null): V1\RemoveUserGroupMemberResponse;

    /**
     * @throws ServiceClientException
     */
    public function GetUserGroupMembers(V1\GetUserGroupMembersRequest $arg, ?ContextInterface $ctx = null): V1\GetUserGroupMembersResponse;

    /**
     * Create a service account.
     *
     * @throws ServiceClientException
     */
    public function CreateServiceAccount(V1\CreateServiceAccountRequest $arg, ?ContextInterface $ctx = null): V1\CreateServiceAccountResponse;

    /**
     * Get a service account.
     *
     * @throws ServiceClientException
     */
    public function GetServiceAccount(V1\GetServiceAccountRequest $arg, ?ContextInterface $ctx = null): V1\GetServiceAccountResponse;

    /**
     * Get service accounts.
     *
     * @throws ServiceClientException
     */
    public function GetServiceAccounts(V1\GetServiceAccountsRequest $arg, ?ContextInterface $ctx = null): V1\GetServiceAccountsResponse;

    /**
     * Update a service account.
     *
     * @throws ServiceClientException
     */
    public function UpdateServiceAccount(V1\UpdateServiceAccountRequest $arg, ?ContextInterface $ctx = null): V1\UpdateServiceAccountResponse;

    /**
     * Set a service account's access to a namespace.
     *
     * @throws ServiceClientException
     */
    public function SetServiceAccountNamespaceAccess(V1\SetServiceAccountNamespaceAccessRequest $arg, ?ContextInterface $ctx = null): V1\SetServiceAccountNamespaceAccessResponse;

    /**
     * Delete a service account.
     *
     * @throws ServiceClientException
     */
    public function DeleteServiceAccount(V1\DeleteServiceAccountRequest $arg, ?ContextInterface $ctx = null): V1\DeleteServiceAccountResponse;

    /**
     * WARNING: Pre-Release Feature
     * Get usage data across namespaces
     *
     * @throws ServiceClientException
     */
    public function GetUsage(V1\GetUsageRequest $arg, ?ContextInterface $ctx = null): V1\GetUsageResponse;

    /**
     * Get account information.
     *
     * @throws ServiceClientException
     */
    public function GetAccount(V1\GetAccountRequest $arg, ?ContextInterface $ctx = null): V1\GetAccountResponse;

    /**
     * Update account information.
     *
     * @throws ServiceClientException
     */
    public function UpdateAccount(V1\UpdateAccountRequest $arg, ?ContextInterface $ctx = null): V1\UpdateAccountResponse;

    /**
     * Create an export sink
     *
     * @throws ServiceClientException
     */
    public function CreateNamespaceExportSink(V1\CreateNamespaceExportSinkRequest $arg, ?ContextInterface $ctx = null): V1\CreateNamespaceExportSinkResponse;

    /**
     * Get an export sink
     *
     * @throws ServiceClientException
     */
    public function GetNamespaceExportSink(V1\GetNamespaceExportSinkRequest $arg, ?ContextInterface $ctx = null): V1\GetNamespaceExportSinkResponse;

    /**
     * Get export sinks
     *
     * @throws ServiceClientException
     */
    public function GetNamespaceExportSinks(V1\GetNamespaceExportSinksRequest $arg, ?ContextInterface $ctx = null): V1\GetNamespaceExportSinksResponse;

    /**
     * Update an export sink
     *
     * @throws ServiceClientException
     */
    public function UpdateNamespaceExportSink(V1\UpdateNamespaceExportSinkRequest $arg, ?ContextInterface $ctx = null): V1\UpdateNamespaceExportSinkResponse;

    /**
     * Delete an export sink
     *
     * @throws ServiceClientException
     */
    public function DeleteNamespaceExportSink(V1\DeleteNamespaceExportSinkRequest $arg, ?ContextInterface $ctx = null): V1\DeleteNamespaceExportSinkResponse;

    /**
     * Validates an export sink configuration by delivering an empty test file to the
     * specified sink.
     * This operation verifies that the sink is correctly configured, accessible, and
     * ready for data export.
     *
     * @throws ServiceClientException
     */
    public function ValidateNamespaceExportSink(V1\ValidateNamespaceExportSinkRequest $arg, ?ContextInterface $ctx = null): V1\ValidateNamespaceExportSinkResponse;

    /**
     * Update the tags for a namespace
     *
     * @throws ServiceClientException
     */
    public function UpdateNamespaceTags(V1\UpdateNamespaceTagsRequest $arg, ?ContextInterface $ctx = null): V1\UpdateNamespaceTagsResponse;

    /**
     * Creates a connectivity rule
     *
     * @throws ServiceClientException
     */
    public function CreateConnectivityRule(V1\CreateConnectivityRuleRequest $arg, ?ContextInterface $ctx = null): V1\CreateConnectivityRuleResponse;

    /**
     * Gets a connectivity rule by id
     *
     * @throws ServiceClientException
     */
    public function GetConnectivityRule(V1\GetConnectivityRuleRequest $arg, ?ContextInterface $ctx = null): V1\GetConnectivityRuleResponse;

    /**
     * Lists connectivity rules by account
     *
     * @throws ServiceClientException
     */
    public function GetConnectivityRules(V1\GetConnectivityRulesRequest $arg, ?ContextInterface $ctx = null): V1\GetConnectivityRulesResponse;

    /**
     * Deletes a connectivity rule by id
     *
     * @throws ServiceClientException
     */
    public function DeleteConnectivityRule(V1\DeleteConnectivityRuleRequest $arg, ?ContextInterface $ctx = null): V1\DeleteConnectivityRuleResponse;

    /**
     * Get audit logs
     *
     * @throws ServiceClientException
     */
    public function GetAuditLogs(V1\GetAuditLogsRequest $arg, ?ContextInterface $ctx = null): V1\GetAuditLogsResponse;

    /**
     * Validate customer audit log sink is accessible from Temporal's workflow by
     * delivering an empty file to the specified sink.
     * The operation verifies that the sink is correctly configured, accessible and
     * ready to receive audit logs.
     *
     * @throws ServiceClientException
     */
    public function ValidateAccountAuditLogSink(V1\ValidateAccountAuditLogSinkRequest $arg, ?ContextInterface $ctx = null): V1\ValidateAccountAuditLogSinkResponse;

    /**
     * Create an audit log sink
     *
     * @throws ServiceClientException
     */
    public function CreateAccountAuditLogSink(V1\CreateAccountAuditLogSinkRequest $arg, ?ContextInterface $ctx = null): V1\CreateAccountAuditLogSinkResponse;

    /**
     * Get an audit log sink
     *
     * @throws ServiceClientException
     */
    public function GetAccountAuditLogSink(V1\GetAccountAuditLogSinkRequest $arg, ?ContextInterface $ctx = null): V1\GetAccountAuditLogSinkResponse;

    /**
     * Get audit log sinks
     *
     * @throws ServiceClientException
     */
    public function GetAccountAuditLogSinks(V1\GetAccountAuditLogSinksRequest $arg, ?ContextInterface $ctx = null): V1\GetAccountAuditLogSinksResponse;

    /**
     * Update an audit log sink
     *
     * @throws ServiceClientException
     */
    public function UpdateAccountAuditLogSink(V1\UpdateAccountAuditLogSinkRequest $arg, ?ContextInterface $ctx = null): V1\UpdateAccountAuditLogSinkResponse;

    /**
     * Delete an audit log sink
     *
     * @throws ServiceClientException
     */
    public function DeleteAccountAuditLogSink(V1\DeleteAccountAuditLogSinkRequest $arg, ?ContextInterface $ctx = null): V1\DeleteAccountAuditLogSinkResponse;

    /**
     * Get namespace capacity information
     *
     * @throws ServiceClientException
     */
    public function GetNamespaceCapacityInfo(V1\GetNamespaceCapacityInfoRequest $arg, ?ContextInterface $ctx = null): V1\GetNamespaceCapacityInfoResponse;

    /**
     * Create a billing report
     *
     * @throws ServiceClientException
     */
    public function CreateBillingReport(V1\CreateBillingReportRequest $arg, ?ContextInterface $ctx = null): V1\CreateBillingReportResponse;

    /**
     * Get a billing report
     *
     * @throws ServiceClientException
     */
    public function GetBillingReport(V1\GetBillingReportRequest $arg, ?ContextInterface $ctx = null): V1\GetBillingReportResponse;

    /**
     * Get custom roles
     *
     * @throws ServiceClientException
     */
    public function GetCustomRoles(V1\GetCustomRolesRequest $arg, ?ContextInterface $ctx = null): V1\GetCustomRolesResponse;

    /**
     * Get a custom role
     *
     * @throws ServiceClientException
     */
    public function GetCustomRole(V1\GetCustomRoleRequest $arg, ?ContextInterface $ctx = null): V1\GetCustomRoleResponse;

    /**
     * Create a custom role
     *
     * @throws ServiceClientException
     */
    public function CreateCustomRole(V1\CreateCustomRoleRequest $arg, ?ContextInterface $ctx = null): V1\CreateCustomRoleResponse;

    /**
     * Update a custom role
     *
     * @throws ServiceClientException
     */
    public function UpdateCustomRole(V1\UpdateCustomRoleRequest $arg, ?ContextInterface $ctx = null): V1\UpdateCustomRoleResponse;

    /**
     * Delete a custom role
     *
     * @throws ServiceClientException
     */
    public function DeleteCustomRole(V1\DeleteCustomRoleRequest $arg, ?ContextInterface $ctx = null): V1\DeleteCustomRoleResponse;

    /**
     * Get users with access to a namespace
     *
     * @throws ServiceClientException
     */
    public function GetUserNamespaceAssignments(V1\GetUserNamespaceAssignmentsRequest $arg, ?ContextInterface $ctx = null): V1\GetUserNamespaceAssignmentsResponse;

    /**
     * Get service accounts with access to a namespace
     *
     * @throws ServiceClientException
     */
    public function GetServiceAccountNamespaceAssignments(V1\GetServiceAccountNamespaceAssignmentsRequest $arg, ?ContextInterface $ctx = null): V1\GetServiceAccountNamespaceAssignmentsResponse;

    /**
     * Get user groups with access to a namespace
     *
     * @throws ServiceClientException
     */
    public function GetUserGroupNamespaceAssignments(V1\GetUserGroupNamespaceAssignmentsRequest $arg, ?ContextInterface $ctx = null): V1\GetUserGroupNamespaceAssignmentsResponse;

    /**
     * Get all projects
     *
     * @throws ServiceClientException
     */
    public function GetProjects(V1\GetProjectsRequest $arg, ?ContextInterface $ctx = null): V1\GetProjectsResponse;

    /**
     * Get a project
     *
     * @throws ServiceClientException
     */
    public function GetProject(V1\GetProjectRequest $arg, ?ContextInterface $ctx = null): V1\GetProjectResponse;

    /**
     * Create a new project
     *
     * @throws ServiceClientException
     */
    public function CreateProject(V1\CreateProjectRequest $arg, ?ContextInterface $ctx = null): V1\CreateProjectResponse;

    /**
     * Update a project
     *
     * @throws ServiceClientException
     */
    public function UpdateProject(V1\UpdateProjectRequest $arg, ?ContextInterface $ctx = null): V1\UpdateProjectResponse;

    /**
     * Delete a project
     *
     * @throws ServiceClientException
     */
    public function DeleteProject(V1\DeleteProjectRequest $arg, ?ContextInterface $ctx = null): V1\DeleteProjectResponse;

    /**
     * Set a user's access to a project
     *
     * @throws ServiceClientException
     */
    public function SetUserProjectAccess(V1\SetUserProjectAccessRequest $arg, ?ContextInterface $ctx = null): V1\SetUserProjectAccessResponse;

    /**
     * Set a user group's access to a project
     *
     * @throws ServiceClientException
     */
    public function SetUserGroupProjectAccess(V1\SetUserGroupProjectAccessRequest $arg, ?ContextInterface $ctx = null): V1\SetUserGroupProjectAccessResponse;

    /**
     * Set a service account's access to a project
     *
     * @throws ServiceClientException
     */
    public function SetServiceAccountProjectAccess(V1\SetServiceAccountProjectAccessRequest $arg, ?ContextInterface $ctx = null): V1\SetServiceAccountProjectAccessResponse;

    /**
     * Get users with access to a project
     *
     * @throws ServiceClientException
     */
    public function GetUserProjectAssignments(V1\GetUserProjectAssignmentsRequest $arg, ?ContextInterface $ctx = null): V1\GetUserProjectAssignmentsResponse;

    /**
     * Get service accounts with access to a project
     *
     * @throws ServiceClientException
     */
    public function GetServiceAccountProjectAssignments(V1\GetServiceAccountProjectAssignmentsRequest $arg, ?ContextInterface $ctx = null): V1\GetServiceAccountProjectAssignmentsResponse;

    /**
     * Get user groups with access to a project
     *
     * @throws ServiceClientException
     */
    public function GetUserGroupProjectAssignments(V1\GetUserGroupProjectAssignmentsRequest $arg, ?ContextInterface $ctx = null): V1\GetUserGroupProjectAssignmentsResponse;

    /**
     * Get service accounts scoped to a project
     *
     * @throws ServiceClientException
     */
    public function GetProjectScopedServiceAccounts(V1\GetProjectScopedServiceAccountsRequest $arg, ?ContextInterface $ctx = null): V1\GetProjectScopedServiceAccountsResponse;
}
