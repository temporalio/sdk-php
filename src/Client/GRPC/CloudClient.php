<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC;

use Temporal\Api\Cloud\Cloudservice\V1;
use Temporal\Exception\Client\ServiceClientException;

class CloudClient extends BaseClient implements CloudClientInterface
{
    /**
     * Get information about the current authenticated user or service account
     * principal
     *
     * @throws ServiceClientException
     */
    public function GetCurrentIdentity(V1\GetCurrentIdentityRequest $arg, ?ContextInterface $ctx = null): V1\GetCurrentIdentityResponse
    {
        return $this->invoke("GetCurrentIdentity", $arg, $ctx);
    }

    /**
     * Gets all known users
     *
     * @throws ServiceClientException
     */
    public function GetUsers(V1\GetUsersRequest $arg, ?ContextInterface $ctx = null): V1\GetUsersResponse
    {
        return $this->invoke("GetUsers", $arg, $ctx);
    }

    /**
     * Get a user
     *
     * @throws ServiceClientException
     */
    public function GetUser(V1\GetUserRequest $arg, ?ContextInterface $ctx = null): V1\GetUserResponse
    {
        return $this->invoke("GetUser", $arg, $ctx);
    }

    /**
     * Create a user
     *
     * @throws ServiceClientException
     */
    public function CreateUser(V1\CreateUserRequest $arg, ?ContextInterface $ctx = null): V1\CreateUserResponse
    {
        return $this->invoke("CreateUser", $arg, $ctx);
    }

    /**
     * Update a user
     *
     * @throws ServiceClientException
     */
    public function UpdateUser(V1\UpdateUserRequest $arg, ?ContextInterface $ctx = null): V1\UpdateUserResponse
    {
        return $this->invoke("UpdateUser", $arg, $ctx);
    }

    /**
     * Delete a user
     *
     * @throws ServiceClientException
     */
    public function DeleteUser(V1\DeleteUserRequest $arg, ?ContextInterface $ctx = null): V1\DeleteUserResponse
    {
        return $this->invoke("DeleteUser", $arg, $ctx);
    }

    /**
     * Set a user's access to a namespace
     *
     * @throws ServiceClientException
     */
    public function SetUserNamespaceAccess(V1\SetUserNamespaceAccessRequest $arg, ?ContextInterface $ctx = null): V1\SetUserNamespaceAccessResponse
    {
        return $this->invoke("SetUserNamespaceAccess", $arg, $ctx);
    }

    /**
     * Get the latest information on an async operation
     *
     * @throws ServiceClientException
     */
    public function GetAsyncOperation(V1\GetAsyncOperationRequest $arg, ?ContextInterface $ctx = null): V1\GetAsyncOperationResponse
    {
        return $this->invoke("GetAsyncOperation", $arg, $ctx);
    }

    /**
     * Create a new namespace
     *
     * @throws ServiceClientException
     */
    public function CreateNamespace(V1\CreateNamespaceRequest $arg, ?ContextInterface $ctx = null): V1\CreateNamespaceResponse
    {
        return $this->invoke("CreateNamespace", $arg, $ctx);
    }

    /**
     * Get all namespaces
     *
     * @throws ServiceClientException
     */
    public function GetNamespaces(V1\GetNamespacesRequest $arg, ?ContextInterface $ctx = null): V1\GetNamespacesResponse
    {
        return $this->invoke("GetNamespaces", $arg, $ctx);
    }

    /**
     * Get a namespace
     *
     * @throws ServiceClientException
     */
    public function GetNamespace(V1\GetNamespaceRequest $arg, ?ContextInterface $ctx = null): V1\GetNamespaceResponse
    {
        return $this->invoke("GetNamespace", $arg, $ctx);
    }

    /**
     * Update a namespace
     *
     * @throws ServiceClientException
     */
    public function UpdateNamespace(V1\UpdateNamespaceRequest $arg, ?ContextInterface $ctx = null): V1\UpdateNamespaceResponse
    {
        return $this->invoke("UpdateNamespace", $arg, $ctx);
    }

    /**
     * Rename an existing customer search attribute
     *
     * @throws ServiceClientException
     */
    public function RenameCustomSearchAttribute(V1\RenameCustomSearchAttributeRequest $arg, ?ContextInterface $ctx = null): V1\RenameCustomSearchAttributeResponse
    {
        return $this->invoke("RenameCustomSearchAttribute", $arg, $ctx);
    }

    /**
     * Delete a namespace
     *
     * @throws ServiceClientException
     */
    public function DeleteNamespace(V1\DeleteNamespaceRequest $arg, ?ContextInterface $ctx = null): V1\DeleteNamespaceResponse
    {
        return $this->invoke("DeleteNamespace", $arg, $ctx);
    }

    /**
     * Failover a multi-region namespace
     *
     * @throws ServiceClientException
     */
    public function FailoverNamespaceRegion(V1\FailoverNamespaceRegionRequest $arg, ?ContextInterface $ctx = null): V1\FailoverNamespaceRegionResponse
    {
        return $this->invoke("FailoverNamespaceRegion", $arg, $ctx);
    }

    /**
     * @throws ServiceClientException
     */
    public function AddNamespaceRegion(V1\AddNamespaceRegionRequest $arg, ?ContextInterface $ctx = null): V1\AddNamespaceRegionResponse
    {
        return $this->invoke("AddNamespaceRegion", $arg, $ctx);
    }

    /**
     * @throws ServiceClientException
     */
    public function DeleteNamespaceRegion(V1\DeleteNamespaceRegionRequest $arg, ?ContextInterface $ctx = null): V1\DeleteNamespaceRegionResponse
    {
        return $this->invoke("DeleteNamespaceRegion", $arg, $ctx);
    }

    /**
     * Get all regions
     *
     * @throws ServiceClientException
     */
    public function GetRegions(V1\GetRegionsRequest $arg, ?ContextInterface $ctx = null): V1\GetRegionsResponse
    {
        return $this->invoke("GetRegions", $arg, $ctx);
    }

    /**
     * Get a region
     *
     * @throws ServiceClientException
     */
    public function GetRegion(V1\GetRegionRequest $arg, ?ContextInterface $ctx = null): V1\GetRegionResponse
    {
        return $this->invoke("GetRegion", $arg, $ctx);
    }

    /**
     * Get all known API keys
     *
     * @throws ServiceClientException
     */
    public function GetApiKeys(V1\GetApiKeysRequest $arg, ?ContextInterface $ctx = null): V1\GetApiKeysResponse
    {
        return $this->invoke("GetApiKeys", $arg, $ctx);
    }

    /**
     * Get an API key
     *
     * @throws ServiceClientException
     */
    public function GetApiKey(V1\GetApiKeyRequest $arg, ?ContextInterface $ctx = null): V1\GetApiKeyResponse
    {
        return $this->invoke("GetApiKey", $arg, $ctx);
    }

    /**
     * Create an API key
     *
     * @throws ServiceClientException
     */
    public function CreateApiKey(V1\CreateApiKeyRequest $arg, ?ContextInterface $ctx = null): V1\CreateApiKeyResponse
    {
        return $this->invoke("CreateApiKey", $arg, $ctx);
    }

    /**
     * Update an API key
     *
     * @throws ServiceClientException
     */
    public function UpdateApiKey(V1\UpdateApiKeyRequest $arg, ?ContextInterface $ctx = null): V1\UpdateApiKeyResponse
    {
        return $this->invoke("UpdateApiKey", $arg, $ctx);
    }

    /**
     * Delete an API key
     *
     * @throws ServiceClientException
     */
    public function DeleteApiKey(V1\DeleteApiKeyRequest $arg, ?ContextInterface $ctx = null): V1\DeleteApiKeyResponse
    {
        return $this->invoke("DeleteApiKey", $arg, $ctx);
    }

    /**
     * Gets nexus endpoints
     *
     * @throws ServiceClientException
     */
    public function GetNexusEndpoints(V1\GetNexusEndpointsRequest $arg, ?ContextInterface $ctx = null): V1\GetNexusEndpointsResponse
    {
        return $this->invoke("GetNexusEndpoints", $arg, $ctx);
    }

    /**
     * Get a nexus endpoint
     *
     * @throws ServiceClientException
     */
    public function GetNexusEndpoint(V1\GetNexusEndpointRequest $arg, ?ContextInterface $ctx = null): V1\GetNexusEndpointResponse
    {
        return $this->invoke("GetNexusEndpoint", $arg, $ctx);
    }

    /**
     * Create a nexus endpoint
     *
     * @throws ServiceClientException
     */
    public function CreateNexusEndpoint(V1\CreateNexusEndpointRequest $arg, ?ContextInterface $ctx = null): V1\CreateNexusEndpointResponse
    {
        return $this->invoke("CreateNexusEndpoint", $arg, $ctx);
    }

    /**
     * Update a nexus endpoint
     *
     * @throws ServiceClientException
     */
    public function UpdateNexusEndpoint(V1\UpdateNexusEndpointRequest $arg, ?ContextInterface $ctx = null): V1\UpdateNexusEndpointResponse
    {
        return $this->invoke("UpdateNexusEndpoint", $arg, $ctx);
    }

    /**
     * Delete a nexus endpoint
     *
     * @throws ServiceClientException
     */
    public function DeleteNexusEndpoint(V1\DeleteNexusEndpointRequest $arg, ?ContextInterface $ctx = null): V1\DeleteNexusEndpointResponse
    {
        return $this->invoke("DeleteNexusEndpoint", $arg, $ctx);
    }

    /**
     * Get all user groups
     *
     * @throws ServiceClientException
     */
    public function GetUserGroups(V1\GetUserGroupsRequest $arg, ?ContextInterface $ctx = null): V1\GetUserGroupsResponse
    {
        return $this->invoke("GetUserGroups", $arg, $ctx);
    }

    /**
     * Get a user group
     *
     * @throws ServiceClientException
     */
    public function GetUserGroup(V1\GetUserGroupRequest $arg, ?ContextInterface $ctx = null): V1\GetUserGroupResponse
    {
        return $this->invoke("GetUserGroup", $arg, $ctx);
    }

    /**
     * Create new a user group
     *
     * @throws ServiceClientException
     */
    public function CreateUserGroup(V1\CreateUserGroupRequest $arg, ?ContextInterface $ctx = null): V1\CreateUserGroupResponse
    {
        return $this->invoke("CreateUserGroup", $arg, $ctx);
    }

    /**
     * Update a user group
     *
     * @throws ServiceClientException
     */
    public function UpdateUserGroup(V1\UpdateUserGroupRequest $arg, ?ContextInterface $ctx = null): V1\UpdateUserGroupResponse
    {
        return $this->invoke("UpdateUserGroup", $arg, $ctx);
    }

    /**
     * Delete a user group
     *
     * @throws ServiceClientException
     */
    public function DeleteUserGroup(V1\DeleteUserGroupRequest $arg, ?ContextInterface $ctx = null): V1\DeleteUserGroupResponse
    {
        return $this->invoke("DeleteUserGroup", $arg, $ctx);
    }

    /**
     * Set a user group's access to a namespace
     *
     * @throws ServiceClientException
     */
    public function SetUserGroupNamespaceAccess(V1\SetUserGroupNamespaceAccessRequest $arg, ?ContextInterface $ctx = null): V1\SetUserGroupNamespaceAccessResponse
    {
        return $this->invoke("SetUserGroupNamespaceAccess", $arg, $ctx);
    }

    /**
     * Add a member to the group, can only be used with Cloud group types.
     *
     * @throws ServiceClientException
     */
    public function AddUserGroupMember(V1\AddUserGroupMemberRequest $arg, ?ContextInterface $ctx = null): V1\AddUserGroupMemberResponse
    {
        return $this->invoke("AddUserGroupMember", $arg, $ctx);
    }

    /**
     * Remove a member from the group, can only be used with Cloud group types.
     *
     * @throws ServiceClientException
     */
    public function RemoveUserGroupMember(V1\RemoveUserGroupMemberRequest $arg, ?ContextInterface $ctx = null): V1\RemoveUserGroupMemberResponse
    {
        return $this->invoke("RemoveUserGroupMember", $arg, $ctx);
    }

    /**
     * @throws ServiceClientException
     */
    public function GetUserGroupMembers(V1\GetUserGroupMembersRequest $arg, ?ContextInterface $ctx = null): V1\GetUserGroupMembersResponse
    {
        return $this->invoke("GetUserGroupMembers", $arg, $ctx);
    }

    /**
     * Create a service account.
     *
     * @throws ServiceClientException
     */
    public function CreateServiceAccount(V1\CreateServiceAccountRequest $arg, ?ContextInterface $ctx = null): V1\CreateServiceAccountResponse
    {
        return $this->invoke("CreateServiceAccount", $arg, $ctx);
    }

    /**
     * Get a service account.
     *
     * @throws ServiceClientException
     */
    public function GetServiceAccount(V1\GetServiceAccountRequest $arg, ?ContextInterface $ctx = null): V1\GetServiceAccountResponse
    {
        return $this->invoke("GetServiceAccount", $arg, $ctx);
    }

    /**
     * Get service accounts.
     *
     * @throws ServiceClientException
     */
    public function GetServiceAccounts(V1\GetServiceAccountsRequest $arg, ?ContextInterface $ctx = null): V1\GetServiceAccountsResponse
    {
        return $this->invoke("GetServiceAccounts", $arg, $ctx);
    }

    /**
     * Update a service account.
     *
     * @throws ServiceClientException
     */
    public function UpdateServiceAccount(V1\UpdateServiceAccountRequest $arg, ?ContextInterface $ctx = null): V1\UpdateServiceAccountResponse
    {
        return $this->invoke("UpdateServiceAccount", $arg, $ctx);
    }

    /**
     * Set a service account's access to a namespace.
     *
     * @throws ServiceClientException
     */
    public function SetServiceAccountNamespaceAccess(V1\SetServiceAccountNamespaceAccessRequest $arg, ?ContextInterface $ctx = null): V1\SetServiceAccountNamespaceAccessResponse
    {
        return $this->invoke("SetServiceAccountNamespaceAccess", $arg, $ctx);
    }

    /**
     * Delete a service account.
     *
     * @throws ServiceClientException
     */
    public function DeleteServiceAccount(V1\DeleteServiceAccountRequest $arg, ?ContextInterface $ctx = null): V1\DeleteServiceAccountResponse
    {
        return $this->invoke("DeleteServiceAccount", $arg, $ctx);
    }

    /**
     * WARNING: Pre-Release Feature
     * Get usage data across namespaces
     *
     * @throws ServiceClientException
     */
    public function GetUsage(V1\GetUsageRequest $arg, ?ContextInterface $ctx = null): V1\GetUsageResponse
    {
        return $this->invoke("GetUsage", $arg, $ctx);
    }

    /**
     * Get account information.
     *
     * @throws ServiceClientException
     */
    public function GetAccount(V1\GetAccountRequest $arg, ?ContextInterface $ctx = null): V1\GetAccountResponse
    {
        return $this->invoke("GetAccount", $arg, $ctx);
    }

    /**
     * Update account information.
     *
     * @throws ServiceClientException
     */
    public function UpdateAccount(V1\UpdateAccountRequest $arg, ?ContextInterface $ctx = null): V1\UpdateAccountResponse
    {
        return $this->invoke("UpdateAccount", $arg, $ctx);
    }

    /**
     * Create an export sink
     *
     * @throws ServiceClientException
     */
    public function CreateNamespaceExportSink(V1\CreateNamespaceExportSinkRequest $arg, ?ContextInterface $ctx = null): V1\CreateNamespaceExportSinkResponse
    {
        return $this->invoke("CreateNamespaceExportSink", $arg, $ctx);
    }

    /**
     * Get an export sink
     *
     * @throws ServiceClientException
     */
    public function GetNamespaceExportSink(V1\GetNamespaceExportSinkRequest $arg, ?ContextInterface $ctx = null): V1\GetNamespaceExportSinkResponse
    {
        return $this->invoke("GetNamespaceExportSink", $arg, $ctx);
    }

    /**
     * Get export sinks
     *
     * @throws ServiceClientException
     */
    public function GetNamespaceExportSinks(V1\GetNamespaceExportSinksRequest $arg, ?ContextInterface $ctx = null): V1\GetNamespaceExportSinksResponse
    {
        return $this->invoke("GetNamespaceExportSinks", $arg, $ctx);
    }

    /**
     * Update an export sink
     *
     * @throws ServiceClientException
     */
    public function UpdateNamespaceExportSink(V1\UpdateNamespaceExportSinkRequest $arg, ?ContextInterface $ctx = null): V1\UpdateNamespaceExportSinkResponse
    {
        return $this->invoke("UpdateNamespaceExportSink", $arg, $ctx);
    }

    /**
     * Delete an export sink
     *
     * @throws ServiceClientException
     */
    public function DeleteNamespaceExportSink(V1\DeleteNamespaceExportSinkRequest $arg, ?ContextInterface $ctx = null): V1\DeleteNamespaceExportSinkResponse
    {
        return $this->invoke("DeleteNamespaceExportSink", $arg, $ctx);
    }

    /**
     * Validates an export sink configuration by delivering an empty test file to the
     * specified sink.
     * This operation verifies that the sink is correctly configured, accessible, and
     * ready for data export.
     *
     * @throws ServiceClientException
     */
    public function ValidateNamespaceExportSink(V1\ValidateNamespaceExportSinkRequest $arg, ?ContextInterface $ctx = null): V1\ValidateNamespaceExportSinkResponse
    {
        return $this->invoke("ValidateNamespaceExportSink", $arg, $ctx);
    }

    /**
     * Update the tags for a namespace
     *
     * @throws ServiceClientException
     */
    public function UpdateNamespaceTags(V1\UpdateNamespaceTagsRequest $arg, ?ContextInterface $ctx = null): V1\UpdateNamespaceTagsResponse
    {
        return $this->invoke("UpdateNamespaceTags", $arg, $ctx);
    }

    /**
     * Creates a connectivity rule
     *
     * @throws ServiceClientException
     */
    public function CreateConnectivityRule(V1\CreateConnectivityRuleRequest $arg, ?ContextInterface $ctx = null): V1\CreateConnectivityRuleResponse
    {
        return $this->invoke("CreateConnectivityRule", $arg, $ctx);
    }

    /**
     * Gets a connectivity rule by id
     *
     * @throws ServiceClientException
     */
    public function GetConnectivityRule(V1\GetConnectivityRuleRequest $arg, ?ContextInterface $ctx = null): V1\GetConnectivityRuleResponse
    {
        return $this->invoke("GetConnectivityRule", $arg, $ctx);
    }

    /**
     * Lists connectivity rules by account
     *
     * @throws ServiceClientException
     */
    public function GetConnectivityRules(V1\GetConnectivityRulesRequest $arg, ?ContextInterface $ctx = null): V1\GetConnectivityRulesResponse
    {
        return $this->invoke("GetConnectivityRules", $arg, $ctx);
    }

    /**
     * Deletes a connectivity rule by id
     *
     * @throws ServiceClientException
     */
    public function DeleteConnectivityRule(V1\DeleteConnectivityRuleRequest $arg, ?ContextInterface $ctx = null): V1\DeleteConnectivityRuleResponse
    {
        return $this->invoke("DeleteConnectivityRule", $arg, $ctx);
    }

    /**
     * Get audit logs
     *
     * @throws ServiceClientException
     */
    public function GetAuditLogs(V1\GetAuditLogsRequest $arg, ?ContextInterface $ctx = null): V1\GetAuditLogsResponse
    {
        return $this->invoke("GetAuditLogs", $arg, $ctx);
    }

    /**
     * Validate customer audit log sink is accessible from Temporal's workflow by
     * delivering an empty file to the specified sink.
     * The operation verifies that the sink is correctly configured, accessible and
     * ready to receive audit logs.
     *
     * @throws ServiceClientException
     */
    public function ValidateAccountAuditLogSink(V1\ValidateAccountAuditLogSinkRequest $arg, ?ContextInterface $ctx = null): V1\ValidateAccountAuditLogSinkResponse
    {
        return $this->invoke("ValidateAccountAuditLogSink", $arg, $ctx);
    }

    /**
     * Create an audit log sink
     *
     * @throws ServiceClientException
     */
    public function CreateAccountAuditLogSink(V1\CreateAccountAuditLogSinkRequest $arg, ?ContextInterface $ctx = null): V1\CreateAccountAuditLogSinkResponse
    {
        return $this->invoke("CreateAccountAuditLogSink", $arg, $ctx);
    }

    /**
     * Get an audit log sink
     *
     * @throws ServiceClientException
     */
    public function GetAccountAuditLogSink(V1\GetAccountAuditLogSinkRequest $arg, ?ContextInterface $ctx = null): V1\GetAccountAuditLogSinkResponse
    {
        return $this->invoke("GetAccountAuditLogSink", $arg, $ctx);
    }

    /**
     * Get audit log sinks
     *
     * @throws ServiceClientException
     */
    public function GetAccountAuditLogSinks(V1\GetAccountAuditLogSinksRequest $arg, ?ContextInterface $ctx = null): V1\GetAccountAuditLogSinksResponse
    {
        return $this->invoke("GetAccountAuditLogSinks", $arg, $ctx);
    }

    /**
     * Update an audit log sink
     *
     * @throws ServiceClientException
     */
    public function UpdateAccountAuditLogSink(V1\UpdateAccountAuditLogSinkRequest $arg, ?ContextInterface $ctx = null): V1\UpdateAccountAuditLogSinkResponse
    {
        return $this->invoke("UpdateAccountAuditLogSink", $arg, $ctx);
    }

    /**
     * Delete an audit log sink
     *
     * @throws ServiceClientException
     */
    public function DeleteAccountAuditLogSink(V1\DeleteAccountAuditLogSinkRequest $arg, ?ContextInterface $ctx = null): V1\DeleteAccountAuditLogSinkResponse
    {
        return $this->invoke("DeleteAccountAuditLogSink", $arg, $ctx);
    }

    /**
     * Get namespace capacity information
     *
     * @throws ServiceClientException
     */
    public function GetNamespaceCapacityInfo(V1\GetNamespaceCapacityInfoRequest $arg, ?ContextInterface $ctx = null): V1\GetNamespaceCapacityInfoResponse
    {
        return $this->invoke("GetNamespaceCapacityInfo", $arg, $ctx);
    }

    /**
     * Create a billing report
     *
     * @throws ServiceClientException
     */
    public function CreateBillingReport(V1\CreateBillingReportRequest $arg, ?ContextInterface $ctx = null): V1\CreateBillingReportResponse
    {
        return $this->invoke("CreateBillingReport", $arg, $ctx);
    }

    /**
     * Get a billing report
     *
     * @throws ServiceClientException
     */
    public function GetBillingReport(V1\GetBillingReportRequest $arg, ?ContextInterface $ctx = null): V1\GetBillingReportResponse
    {
        return $this->invoke("GetBillingReport", $arg, $ctx);
    }

    /**
     * Get custom roles
     *
     * @throws ServiceClientException
     */
    public function GetCustomRoles(V1\GetCustomRolesRequest $arg, ?ContextInterface $ctx = null): V1\GetCustomRolesResponse
    {
        return $this->invoke("GetCustomRoles", $arg, $ctx);
    }

    /**
     * Get a custom role
     *
     * @throws ServiceClientException
     */
    public function GetCustomRole(V1\GetCustomRoleRequest $arg, ?ContextInterface $ctx = null): V1\GetCustomRoleResponse
    {
        return $this->invoke("GetCustomRole", $arg, $ctx);
    }

    /**
     * Create a custom role
     *
     * @throws ServiceClientException
     */
    public function CreateCustomRole(V1\CreateCustomRoleRequest $arg, ?ContextInterface $ctx = null): V1\CreateCustomRoleResponse
    {
        return $this->invoke("CreateCustomRole", $arg, $ctx);
    }

    /**
     * Update a custom role
     *
     * @throws ServiceClientException
     */
    public function UpdateCustomRole(V1\UpdateCustomRoleRequest $arg, ?ContextInterface $ctx = null): V1\UpdateCustomRoleResponse
    {
        return $this->invoke("UpdateCustomRole", $arg, $ctx);
    }

    /**
     * Delete a custom role
     *
     * @throws ServiceClientException
     */
    public function DeleteCustomRole(V1\DeleteCustomRoleRequest $arg, ?ContextInterface $ctx = null): V1\DeleteCustomRoleResponse
    {
        return $this->invoke("DeleteCustomRole", $arg, $ctx);
    }

    /**
     * Get users with access to a namespace
     *
     * @throws ServiceClientException
     */
    public function GetUserNamespaceAssignments(V1\GetUserNamespaceAssignmentsRequest $arg, ?ContextInterface $ctx = null): V1\GetUserNamespaceAssignmentsResponse
    {
        return $this->invoke("GetUserNamespaceAssignments", $arg, $ctx);
    }

    /**
     * Get service accounts with access to a namespace
     *
     * @throws ServiceClientException
     */
    public function GetServiceAccountNamespaceAssignments(V1\GetServiceAccountNamespaceAssignmentsRequest $arg, ?ContextInterface $ctx = null): V1\GetServiceAccountNamespaceAssignmentsResponse
    {
        return $this->invoke("GetServiceAccountNamespaceAssignments", $arg, $ctx);
    }

    /**
     * Get user groups with access to a namespace
     *
     * @throws ServiceClientException
     */
    public function GetUserGroupNamespaceAssignments(V1\GetUserGroupNamespaceAssignmentsRequest $arg, ?ContextInterface $ctx = null): V1\GetUserGroupNamespaceAssignmentsResponse
    {
        return $this->invoke("GetUserGroupNamespaceAssignments", $arg, $ctx);
    }

    /**
     * Get all projects
     *
     * @throws ServiceClientException
     */
    public function GetProjects(V1\GetProjectsRequest $arg, ?ContextInterface $ctx = null): V1\GetProjectsResponse
    {
        return $this->invoke("GetProjects", $arg, $ctx);
    }

    /**
     * Get a project
     *
     * @throws ServiceClientException
     */
    public function GetProject(V1\GetProjectRequest $arg, ?ContextInterface $ctx = null): V1\GetProjectResponse
    {
        return $this->invoke("GetProject", $arg, $ctx);
    }

    /**
     * Create a new project
     *
     * @throws ServiceClientException
     */
    public function CreateProject(V1\CreateProjectRequest $arg, ?ContextInterface $ctx = null): V1\CreateProjectResponse
    {
        return $this->invoke("CreateProject", $arg, $ctx);
    }

    /**
     * Update a project
     *
     * @throws ServiceClientException
     */
    public function UpdateProject(V1\UpdateProjectRequest $arg, ?ContextInterface $ctx = null): V1\UpdateProjectResponse
    {
        return $this->invoke("UpdateProject", $arg, $ctx);
    }

    /**
     * Delete a project
     *
     * @throws ServiceClientException
     */
    public function DeleteProject(V1\DeleteProjectRequest $arg, ?ContextInterface $ctx = null): V1\DeleteProjectResponse
    {
        return $this->invoke("DeleteProject", $arg, $ctx);
    }

    /**
     * Set a user's access to a project
     *
     * @throws ServiceClientException
     */
    public function SetUserProjectAccess(V1\SetUserProjectAccessRequest $arg, ?ContextInterface $ctx = null): V1\SetUserProjectAccessResponse
    {
        return $this->invoke("SetUserProjectAccess", $arg, $ctx);
    }

    /**
     * Set a user group's access to a project
     *
     * @throws ServiceClientException
     */
    public function SetUserGroupProjectAccess(V1\SetUserGroupProjectAccessRequest $arg, ?ContextInterface $ctx = null): V1\SetUserGroupProjectAccessResponse
    {
        return $this->invoke("SetUserGroupProjectAccess", $arg, $ctx);
    }

    /**
     * Set a service account's access to a project
     *
     * @throws ServiceClientException
     */
    public function SetServiceAccountProjectAccess(V1\SetServiceAccountProjectAccessRequest $arg, ?ContextInterface $ctx = null): V1\SetServiceAccountProjectAccessResponse
    {
        return $this->invoke("SetServiceAccountProjectAccess", $arg, $ctx);
    }

    /**
     * Get users with access to a project
     *
     * @throws ServiceClientException
     */
    public function GetUserProjectAssignments(V1\GetUserProjectAssignmentsRequest $arg, ?ContextInterface $ctx = null): V1\GetUserProjectAssignmentsResponse
    {
        return $this->invoke("GetUserProjectAssignments", $arg, $ctx);
    }

    /**
     * Get service accounts with access to a project
     *
     * @throws ServiceClientException
     */
    public function GetServiceAccountProjectAssignments(V1\GetServiceAccountProjectAssignmentsRequest $arg, ?ContextInterface $ctx = null): V1\GetServiceAccountProjectAssignmentsResponse
    {
        return $this->invoke("GetServiceAccountProjectAssignments", $arg, $ctx);
    }

    /**
     * Get user groups with access to a project
     *
     * @throws ServiceClientException
     */
    public function GetUserGroupProjectAssignments(V1\GetUserGroupProjectAssignmentsRequest $arg, ?ContextInterface $ctx = null): V1\GetUserGroupProjectAssignmentsResponse
    {
        return $this->invoke("GetUserGroupProjectAssignments", $arg, $ctx);
    }

    /**
     * Get service accounts scoped to a project
     *
     * @throws ServiceClientException
     */
    public function GetProjectScopedServiceAccounts(V1\GetProjectScopedServiceAccountsRequest $arg, ?ContextInterface $ctx = null): V1\GetProjectScopedServiceAccountsResponse
    {
        return $this->invoke("GetProjectScopedServiceAccounts", $arg, $ctx);
    }

    /**
     * Pin the Cloud Operations API version.
     *
     * Sets the `temporal-cloud-api-version` header for every call made by the returned
     * client.
     *
     * @link https://docs.temporal.io/ops
     */
    public function withApiVersion(string $version): static
    {
        $context = $this->getContext();

        return $this->withContext(
            $context->withMetadata(
                $context->getMetadata() + ['temporal-cloud-api-version' => [$version]],
            ),
        );
    }

    protected static function createGrpcStub(string $address, array $options): \Grpc\BaseStub
    {
        return new V1\CloudServiceClient($address, $options);
    }
}
