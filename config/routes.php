<?php

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

$collection = new RouteCollection();

/** Default route for AWS ElasticBeanstalk Health check */
$collection->add('home', new Route('/', ['_controller' => 'App\Controller\HealthCheckController:checkAction']));

/** Search, participants directory */
$collection->add('search', new Route('/search', ['_controller' => 'App\Controller\ParticipantsDirectoryController:searchAction']));

/** Users */
$collection->add('users.auth', new Route('/users/auth', ['_controller' => 'App\Controller\AuthController:authAction']));
$collection->add('users.second_factor', new Route('/users/auth-code', ['_controller' => 'App\Controller\AuthController:secondFactorAuthAction']));
$collection->add('users.resend_two_factor_code', new Route('/users/resend-auth-code', ['_controller' => 'App\Controller\AuthController:resendTwoFactorCodeAction']));
$collection->add('users.logout', new Route('/users/logout', ['_controller' => 'App\Controller\AuthController:logoutAction']));
$collection->add('users.check', new Route('/users/check', ['_controller' => 'App\Controller\AuthController:checkTokenAction']));

$collection->add('users.data', new Route('/users/data', ['_controller' => 'App\Controller\UsersController:dataAction']));
$collection->add('users.password', new Route('/users/password', ['_controller' => 'App\Controller\UsersController:passwordAction']));
$collection->add('users.avatar', new Route('/users/avatar', ['_controller' => 'App\Controller\UsersController:avatarAction']));
$collection->add('users.profile.edit', new Route('/users/profile/edit', ['_controller' => 'App\Controller\UsersController:editAction']));
$collection->add('users.profile.password', new Route('/users/profile/password', ['_controller' => 'App\Controller\UsersController:changePasswordAction']));
$collection->add('users.timezone', new Route('/users/timezone', ['_controller' => 'App\Controller\UsersController:timeZoneAction']));
$collection->add('users.settings', new Route('/users/settings', ['_controller' => 'App\Controller\UsersController:settingsAction']));
$collection->add('users.access', new Route('/users/access', ['_controller' => 'App\Controller\UsersController:accessAction']));
$collection->add('confirmation', new Route('/confirmation', ['_controller' => 'App\Controller\UsersController:confirmationAction']));
$collection->add('confirmation.check', new Route('/confirmation/check', ['_controller' => 'App\Controller\UsersController:checkConfirmationAction']));
$collection->add('users.check_password', new Route('/users/checkpassword', ['_controller' => 'App\Controller\UsersController:checkPasswordAction']));
$collection->add('users.delete', new Route('/users/delete', ['_controller' => 'App\Controller\UsersController:deleteAction']));
$collection->add('users.timezones.index', new Route('/users/timezones/index', ['_controller' => 'App\Controller\UsersController:timezonesIndexAction']));
$collection->add('users.get_for_clone', new Route('/users/profile/get-for-clone/{participantId}', ['_controller' => 'App\Controller\UsersController:getForNewParticipantAction']));

/** Case managers */
$collection->add('managers.get-manager-data', new Route('/case-manager-data/{managerUserId}', ['_controller' => 'App\Controller\CaseManagersController:getManagerDataAction']));
$collection->add('managers.index', new Route('/case-managers/index', ['_controller' => 'App\Controller\CaseManagersController:indexAction']));

/** Participants */
$collection->add('participant.dashboard', new Route('/participant/dashboard', ['_controller' => 'App\Controller\ParticipantController:dashboardAction']));

/** Forms & form builder */

$collection->add('form.get', new Route('/form/get', ['_controller' => 'App\Controller\FormController:getByIdAction'])); // get form schema, settings etc
$collection->add('form.get_by_ids', new Route('/form/getByIds', ['_controller' => 'App\Controller\FormController:getByIdsAction']));
$collection->add('form.get_for_multiple_organizations', new Route('/forms/get-for-multiple-organizations', ['_controller' => 'App\Controller\FormController:getForMultipleOrganizationsAction']));

$collection->add('form.create', new Route('/form/create', ['_controller' => 'App\Controller\FormController:createAction']));
$collection->add('form.update', new Route('/form/update', ['_controller' => 'App\Controller\FormController:updateAction']));
$collection->add('form.delete', new Route('/form/delete', ['_controller' => 'App\Controller\FormController:deleteAction']));
$collection->add('form.publish', new Route('/form/publish', ['_controller' => 'App\Controller\FormController:togglePublishAction']));
$collection->add('form.block', new Route('/form/block', ['_controller' => 'App\Controller\FormController:toggleBlockAction']));

$collection->add('form.update-shared-fields', new Route('/form/update-shared-fields', ['_controller' => 'App\Controller\FormController:updateSharedFieldsAction']));
$collection->add('form.calculate', new Route('/form/calculate', ['_controller' => 'App\Controller\FormController:calculateAction']));

$collection->add('form-builder.get_modules', new Route('/form-builder/modules', ['_controller' => 'App\Controller\FormBuilderController:modulesIndexAction']));
$collection->add('form-builder.system_forms', new Route('/form-builder/system-forms', ['_controller' => 'App\Controller\FormBuilderController:getSystemFormsAction']));
$collection->add('form-builder.participant_forms', new Route('/form-builder/participant-forms', ['_controller' => 'App\Controller\FormBuilderController:getParticipantFormsAction']));
$collection->add('form-builder.organization_forms', new Route('/form-builder/organization-forms', ['_controller' => 'App\Controller\FormBuilderController:getOrganizationFormsAction']));
$collection->add('form-builder.forms_templates', new Route('/form-builder/templates', ['_controller' => 'App\Controller\FormBuilderController:getFormsTemplatesAction']));
$collection->add('form-builder.referral-forms', new Route('/form-builder/referral-forms', ['_controller' => 'App\Controller\FormBuilderController:getReferralFormsAction']));

$collection->add('form-builder.get_forms_with_shared_fields', new Route('/form-builder/forms-with-shared-fields', ['_controller' => 'App\Controller\FormBuilderController:getFormsWithSharedFieldsAction']));

$collection->add('form-builder.duplicate', new Route('/form-builder/duplicate', ['_controller' => 'App\Controller\FormBuilderController:duplicateAction']));


/** Forms Data */
$collection->add('form-data.get', new Route('/form/data', ['_controller' => 'App\Controller\FormDataController:getByIdAction']));
$collection->add('form-data.delete', new Route('/forms/data/delete', ['_controller' => 'App\Controller\FormDataController:deleteAction']));
$collection->add('form-data.duplicate', new Route('/forms/data/duplicate', ['_controller' => 'App\Controller\FormDataController:duplicateAction']));
$collection->add('form-data.export-history', new Route('/forms/history/export', ['_controller' => 'App\Controller\FormDataController:exportHistoryAction']));
$collection->add('form-data.update-values', new Route('/form/update-values', ['_controller' => 'App\Controller\FormValuesController:updateAction']));
$collection->add('form-data.create-values', new Route('/form/create-values', ['_controller' => 'App\Controller\FormValuesController:createAction']));
$collection->add('form-data.download-file', new Route('/form/download/file/{fileName}', ['_controller' => 'App\Controller\FormDataController:downloadFileAction']));
$collection->add('form-data.print', new Route('/form/print', ['_controller' => 'App\Controller\FormDataController:printAction']));
$collection->add('form-data.grouped', new Route('/form/grouped', ['_controller' => 'App\Controller\FormDataController:groupedAction'])); // FormCRUD widget
$collection->add('form-data.preview', new Route('/forms/preview', ['_controller' => 'App\Controller\FormsPreviewController:previewAction'])); // FormCRUD widget - "SHOW ALL"

/** Modules */
$collection->add('modules.forms', new Route('/modules/forms', ['_controller' => 'App\Controller\ModulesController:FormsAction']));
$collection->add('modules.confirm-current-assignment-overwrite', new Route('/modules/confirm-current-assignment-overwrite', ['_controller' => 'App\Controller\ModulesController:confirmCurrentAssignmentOverwriteAction']));

/** Accounts */
$collection->add('accounts.index', new Route('/accounts', ['_controller' => 'App\Controller\AccountsController:indexAction']));
$collection->add('accounts.create', new Route('/accounts/create', ['_controller' => 'App\Controller\AccountsController:createAction']));
$collection->add('accounts.edit', new Route('/accounts/edit/{id}', ['_controller' => 'App\Controller\AccountsController:editAction']));
$collection->add('accounts.create.user', new Route('/accounts/create/{aid}', ['_controller' => 'App\Controller\AccountsController:createUserAction']));
$collection->add('accounts.toggle.user', new Route('/accounts/{id}/toggle/{uid}', ['_controller' => 'App\Controller\AccountsController:toggleUserAction']));
$collection->add('accounts.resend.user', new Route('/accounts/{id}/resend/{uid}', ['_controller' => 'App\Controller\AccountsController:resendUserAction']));
$collection->add('organization.widgets', new Route('/organization/widgets', ['_controller' => 'App\Controller\AccountsController:widgetsAction']));
$collection->add('accounts.export', new Route('/accounts/export', ['_controller' => 'App\Controller\AccountsController:exportAction']));
$collection->add('accounts.export.users', new Route('/accounts/export/users', ['_controller' => 'App\Controller\AccountsController:exportUsersAction']));
$collection->add('accounts.unlink-account', new Route('/accounts/unlink-account', ['_controller' => 'App\Controller\AccountsController:unlinkAccountAction']));
$collection->add('accounts.by_participant_type', new Route('/accounts/by-participant-type/{participantType}', ['_controller' => 'App\Controller\AccountsController:byParticipantTypeAction']));
$collection->add('accounts.program_index', new Route('/accounts/{id}/programs', ['_controller' => 'App\Controller\AccountsController:getProgramsIndexAction']));
$collection->add('accounts.user-access', new Route('/accounts/user-access/{userId}', ['_controller' => 'App\Controller\AccountsController:userAccessAction']));

// viewAs
$collection->add('accounts.view', new Route('/accounts/view', ['_controller' => 'App\Controller\AccountsController:viewAsAction']));
$collection->add('accounts.default', new Route('/accounts/default', ['_controller' => 'App\Controller\AccountsController:setDefaultAction']));

/** Password */
$collection->add('resetting.email', new Route('/resetting/email', ['_controller' => 'App\Controller\ResettingController:emailAction']));
$collection->add('resetting.change', new Route('/resetting/change', ['_controller' => 'App\Controller\ResettingController:changeAction']));

/** Activity Feed */
$collection->add('activity.index', new Route('/activities', ['_controller' => 'App\Controller\ActivityFeedController:indexAction']));
$collection->add('activity.export', new Route('/activities/export', ['_controller' => 'App\Controller\ActivityFeedController:exportAction']));

/** Case Notes */
$collection->add('notes.index', new Route('/notes', ['_controller' => 'App\Controller\CommunicationNotesController:indexAction']));
$collection->add('notes.create', new Route('/notes/create', ['_controller' => 'App\Controller\CommunicationNotesController:createAction']));
$collection->add('notes.edit', new Route('/notes/edit/{id}', ['_controller' => 'App\Controller\CommunicationNotesController:editAction']));
$collection->add('notes.export', new Route('/notes/export', ['_controller' => 'App\Controller\CommunicationNotesController:exportAction']));
$collection->add('notes.history.export', new Route('/notes/history/export', ['_controller' => 'App\Controller\CommunicationNotesController:exportHistoryAction']));
$collection->add('notes.assignment.index', new Route ('/notes/assignment/index', ['_controller' => 'App\Controller\CommunicationNotesController:indexForAssignmentAction']));

/** Reports */
$collection->add('reports.index', new Route('/reports', ['_controller' => 'App\Controller\ReportsController:indexAction']));
$collection->add('reports.delete', new Route('/reports/delete', ['_controller' => 'App\Controller\ReportsController:deleteAction']));
$collection->add('reports.status', new Route('/reports/status', ['_controller' => 'App\Controller\ReportsController:statusAction']));
$collection->add('reports.duplicate', new Route('/reports/duplicate', ['_controller' => 'App\Controller\ReportsController:duplicateAction']));
$collection->add('reports.add', new Route('/reports/add', ['_controller' => 'App\Controller\ReportsController:addAction']));
$collection->add('reports.get', new Route('/reports/get', ['_controller' => 'App\Controller\ReportsController:getAction']));
$collection->add('reports.edit', new Route('/reports/edit', ['_controller' => 'App\Controller\ReportsController:editAction']));
$collection->add('reports.result', new Route('/reports/result', ['_controller' => 'App\Controller\ReportsController:resultAction']));
$collection->add('reports.preview', new Route('/reports/preview', ['_controller' => 'App\Controller\ReportsController:previewAction']));
$collection->add('reports.export', new Route('/reports/export', ['_controller' => 'App\Controller\ReportsController:exportAction']));
$collection->add('reports.download', new Route('/reports/download/{type}/{reportId}', ['_controller' => 'App\Controller\ReportsController:downloadAction']));
$collection->add('reports.create_folder', new Route('/reports/create-folder', ['_controller' => 'App\Controller\ReportsController:createFolderAction']));
$collection->add('reports.delete_folder', new Route('/reports/delete-folder', ['_controller' => 'App\Controller\ReportsController:deleteFolderAction']));
$collection->add('reports.account_folders', new Route('/reports/account-folders', ['_controller' => 'App\Controller\ReportsController:accountFoldersAction']));
$collection->add('reports.move_to_folder', new Route('/reports/move-to-folder', ['_controller' => 'App\Controller\ReportsController:moveToFolderAction']));
$collection->add('reports.edit_folder', new Route('/reports/edit-folder', ['_controller' => 'App\Controller\ReportsController:editFolderAction']));
$collection->add('reports.save_top_reports', new Route('/reports/save-top-reports', ['_controller' => 'App\Controller\ReportsController:saveTopReportsAction']));
$collection->add('reports.index_top_reports', new Route('/reports/index-top-reports', ['_controller' => 'App\Controller\ReportsController:indexTopReportsAction']));
$collection->add('reports.index_forms', new Route('/reports/index-forms/{mode}', ['_controller' => 'App\Controller\ReportsController:indexFormsAction']));

/** Groups */
$collection->add('groups.index', new Route('/groups', ['_controller' => 'App\Controller\GroupsController:indexAction']));
$collection->add('groups.create', new Route('/groups/create', ['_controller' => 'App\Controller\GroupsController:createAction']));

/** Events Calendar */
$collection->add('events.index', new Route('/events/index', ['_controller' => 'App\Controller\EventsController:indexAction']));
$collection->add('events.index.date', new Route('/events/index-by-dates', ['_controller' => 'App\Controller\EventsController:indexByDatesAction']));
$collection->add('events.show', new Route('/events/show', ['_controller' => 'App\Controller\EventsController:showAction']));
$collection->add('events.save', new Route('/events/save', ['_controller' => 'App\Controller\EventsController:saveAction']));
$collection->add('events.export', new Route('/events/export', ['_controller' => 'App\Controller\EventsController:exportAction']));
$collection->add('events.delete', new Route('/events/delete', ['_controller' => 'App\Controller\EventsController:deleteAction']));

/** Messages */
$collection->add('messages.index', new Route('/messages', ['_controller' => 'App\Controller\MessagesController:getAction']));
$collection->add('messages.send', new Route('/messages/send', ['_controller' => 'App\Controller\MessagesController:sendAction']));
$collection->add('messages.receive', new Route('/messages/receive', ['_controller' => 'App\Controller\MessagesController:receiveAction']));
$collection->add('messages.export', new Route('/messages/export', ['_controller' => 'App\Controller\MessagesController:exportAction']));
$collection->add('messages.history.export', new Route('/messages/history/export', ['_controller' => 'App\Controller\MessagesController:exportHistoryAction']));

/** Mass Messages */
$collection->add('mass-messages.send', new Route('/mass-messages/send', ['_controller' => 'App\Controller\MassMessagesController:sendAction']));
$collection->add('mass-messages.search', new Route('/mass-messages/search', ['_controller' => 'App\Controller\MassMessagesHistoryController:searchAction']));
$collection->add('mass-messages.details', new Route('/mass-messages/details/{id}', ['_controller' => 'App\Controller\MassMessagesHistoryController:detailsAction']));
$collection->add('mass-messages.history.export', new Route('/mass-messages/history/export', ['_controller' => 'App\Controller\MassMessagesHistoryController:exportAction']));

/** Messages Callback */
$collection->add('messages.callback.receive', new Route('/messages/callback/receive', ['_controller' => 'App\Controller\MessagesCallbackController:receiveAction']));

/** Imports */
$collection->add('imports.forms', new Route('/imports/forms', ['_controller' => 'App\Controller\ImportsController:formsAction']));
$collection->add('imports.template', new Route('/imports/template', ['_controller' => 'App\Controller\ImportsController:templateAction']));
$collection->add('imports.upload', new Route('/imports/upload', ['_controller' => 'App\Controller\ImportsController:uploadAction']));
$collection->add('imports.pre_validate', new Route('/imports/pre-validate', ['_controller' => 'App\Controller\ImportsController:preValidateAction'])); // unused
$collection->add('imports.create_import', new Route('/imports/create', ['_controller' => 'App\Controller\ImportsController:createImportAction']));
$collection->add('imports.history', new Route('/imports/history', ['_controller' => 'App\Controller\ImportsController:historyAction']));
$collection->add('imports.show', new Route('/imports/show', ['_controller' => 'App\Controller\ImportsController:showAction']));
$collection->add('imports.export_exceptions', new Route('/imports/export-exceptions', ['_controller' => 'App\Controller\ImportsController:exportExceptionsAction']));
$collection->add('imports.run', new Route('/imports/run', ['_controller' => 'App\Controller\ImportsController:runImportAction']));


/** Workspace */
$collection->add('workspace.get_forms', new Route('/workspace/get-forms', ['_controller' => 'App\Controller\WorkspaceController:getFormsAction']));
$collection->add('workspace.files.index', new Route('/workspace/shared-files/', ['_controller' => 'App\Controller\WorkspaceSharedFilesController:indexAction']));
$collection->add('workspace.files.upload', new Route('/workspace/shared-files/upload/', ['_controller' => 'App\Controller\WorkspaceSharedFilesController:uploadAction']));
$collection->add('workspace.files.download', new Route('/workspace/shared-files/download/{id}', ['_controller' => 'App\Controller\WorkspaceSharedFilesController:downloadAction']));
$collection->add('workspace.files.delete', new Route('/workspace/shared-files/delete/{id}', ['_controller' => 'App\Controller\WorkspaceSharedFilesController:deleteAction']));
$collection->add('workspace.files.publish', new Route('/workspace/shared-files/publish/{id}', ['_controller' => 'App\Controller\WorkspaceSharedFilesController:publishAction']));
$collection->add('workspace.files.update_description', new Route('/workspace/shared-files/update-description/{id}', ['_controller' => 'App\Controller\WorkspaceSharedFilesController:updateDescriptionAction']));

$collection->add('workspace.public-files.index', new Route('/workspace/public-files/', ['_controller' => 'App\Controller\WorkspacePublicFilesController:indexAction']));
$collection->add('workspace.public-files.upload', new Route('/workspace/public-files/upload/', ['_controller' => 'App\Controller\WorkspacePublicFilesController:uploadAction']));
$collection->add('workspace.public-files.delete', new Route('/workspace/public-files/delete/{id}', ['_controller' => 'App\Controller\WorkspacePublicFilesController:deleteAction']));
$collection->add('workspace.public-files.publish', new Route('/workspace/public-files/publish/{id}', ['_controller' => 'App\Controller\WorkspacePublicFilesController:publishAction']));
$collection->add('workspace.public-files.update_description', new Route('/workspace/public-files/update-description/{id}', ['_controller' => 'App\Controller\WorkspacePublicFilesController:updateDescriptionAction']));

$collection->add('workspace.public-files.download', new Route('/public-files/{prefix}/{filename}', ['_controller' => 'App\Controller\WorkspacePublicFilesController:downloadAction']));

/** General Settings */
$collection->add('general-settings.all', new Route('/general-settings', ['_controller' => 'App\Controller\GeneralSettingsController:getAllSettingsAction']));
$collection->add('general-settings.save', new Route('/general-settings/save', ['_controller' => 'App\Controller\GeneralSettingsController:saveAction']));

/** Status */
$collection->add('maintenance-mode.status', new Route('status/maintenance-mode', ['_controller' => 'App\Controller\MaintenanceController:statusAction']));

/** Participant Directory Columns */
$collection->add('participant-directory-columns.list', new Route('participant-directory-columns/list', ['_controller' => 'App\Controller\ParticipantDirectoryColumnsController:availableColumnsAction']));
$collection->add('participant-directory-columns.columns', new Route('participant-directory-columns/columns', ['_controller' => 'App\Controller\ParticipantDirectoryColumnsController:currentColumnsAction']));
$collection->add('participant-directory-columns.save', new Route('participant-directory-columns/save', ['_controller' => 'App\Controller\ParticipantDirectoryColumnsController:saveAction']));


$collection->add('programs-create', new Route('/programs/create', ['_controller' => 'App\Controller\ProgramsController:createAction']));
$collection->add('programs-update', new Route('/programs/update', ['_controller' => 'App\Controller\ProgramsController:updateAction']));

$collection->add('emails-templates-index', new Route('/emails-templates/index', ['_controller' => 'App\Controller\EmailsTemplatesController:indexAction']));
$collection->add('emails-templates-create', new Route('/emails-templates/create', ['_controller' => 'App\Controller\EmailsTemplatesController:createAction']));
$collection->add('emails-templates-update', new Route('/emails-templates/update', ['_controller' => 'App\Controller\EmailsTemplatesController:updateAction']));
$collection->add('emails-templates-get', new Route('/emails-templates/get/{templateId}', ['_controller' => 'App\Controller\EmailsTemplatesController:getAction']));
$collection->add('emails-templates-duplicate', new Route('/emails-templates/duplicate', ['_controller' => 'App\Controller\EmailsTemplatesController:duplicateAction']));
$collection->add('emails-templates-delete', new Route('/emails-templates/delete', ['_controller' => 'App\Controller\EmailsTemplatesController:deleteAction']));

$collection->add('email-history-index', new Route('/email-history/index', ['_controller' => 'App\Controller\EmailHistoryController:indexAction']));

$collection->add('new-email-options', new Route('emails/new-email-options', ['_controller' => 'App\Controller\EmailsController:newEmailOptionsAction']));

$collection->add('email-create', new Route('emails/create', ['_controller' => 'App\Controller\EmailsController:createAction']));
$collection->add('email-update', new Route('emails/update', ['_controller' => 'App\Controller\EmailsController:updateAction']));

$collection->add('email-message-get', new Route('emails/get/{emailMessageId}', ['_controller' => 'App\Controller\EmailsController:getAction']));

$collection->add('user-activity-log.index', new Route('/user-activity-log/get/{user}', ['_controller' => 'App\Controller\UsersActivityLogController:indexAction']));
$collection->add('user-activity-log.export', new Route('/user-activity-log/export', ['_controller' => 'App\Controller\UsersActivityLogController:exportAction']));

/** Referral forms */

$collection->add('forms.get-referral', new Route('/forms/get-referral/{uid}', ['_controller' => 'App\Controller\ReferralFormsController:getByUidAction']));
$collection->add('forms.save-referral', new Route('/forms/save-referral', ['_controller' => 'App\Controller\ReferralFormsController:saveAction']));

$collection->add('referrals.index-grouped', new Route('/referrals/index-grouped', ['_controller' => 'App\Controller\ReferralFormsController:getIndexGroupedAction']));
$collection->add('referrals.index', new Route('/referrals/index', ['_controller' => 'App\Controller\ReferralFormsController:getIndexAction']));
$collection->add('referrals.get-by-id', new Route('/referrals/get/{referralId}', ['_controller' => 'App\Controller\ReferralFormsController:getByIdAction']));
$collection->add('referrals.get-for-new-participant', new Route('/referrals/get-for-new-participant/{referralId}', ['_controller' => 'App\Controller\ReferralFormsController:getForNewParticipantAction']));

$collection->add('referrals.set-not-enrolled', new Route('/referrals/set-not-enrolled', ['_controller' => 'App\Controller\ReferralFormsController:setNotEnrolledAction']));

$collection->add('referrals.export-feed-as-csv', new Route('/referrals/export-feed-as-csv', ['_controller' => 'App\Controller\ReferralFormsController:exportFeedAsCsvAction']));

$collection->add('referrals.download-pdf', new Route('/referrals/download-submission', ['_controller' => 'App\Controller\ReferralFormsController:downloadPdfAction']));

$collection->add('reports-summary.get-report-for-summary', new Route('/reports-summary/get-report/{reportId}', ['_controller' => 'App\Controller\ReportsSummaryController:getReportForSummaryAction']));
$collection->add('reports-summary.delete', new Route('/report-summary/delete', ['_controller' => 'App\Controller\ReportsSummaryController:deleteAction']));
$collection->add('reports-summary.preview', new Route('/reports-summary/preview', ['_controller' => 'App\Controller\ReportsSummaryController:previewAction']));
$collection->add('reports-summary.create', new Route('/reports-summary/create', ['_controller' => 'App\Controller\ReportsSummaryController:createAction']));
$collection->add('reports-summary.index', new Route('/report-summary/index', ['_controller' => 'App\Controller\ReportsSummaryController:indexAction']));
$collection->add('reports-summary.edit', new Route('/report-summary/edit', ['_controller' => 'App\Controller\ReportsSummaryController:editAction']));
$collection->add('reports-summary.update', new Route('/report-summary/update', ['_controller' => 'App\Controller\ReportsSummaryController:updateAction']));
$collection->add('reports-summary.has-summary', new Route('/report-summary/report-has-summary/{reportId}', ['_controller' => 'App\Controller\ReportsSummaryController:checkIfReportHasSummaryAction']));

$collection->add('reports-charts.index', new Route('/report/{reportId}/charts', ['_controller' => 'App\Controller\ReportsChartsController:indexAction']));

/** Help and Tutorials */

$collection->add('tutorials.categories-index', new Route('/tutorials/categories-index', ['_controller' => 'App\Controller\TutorialsController:categoriesIndexAction']));
$collection->add('tutorials.add-category', new Route('/tutorials/add-category', ['_controller' => 'App\Controller\TutorialsController:addCategoryAction']));
$collection->add('tutorials.get-category', new Route('/tutorials/category/{categoryId}', ['_controller' => 'App\Controller\TutorialsController:getCategoryAction']));
$collection->add('tutorials.remove-category', new Route('/tutorials/remove-category', ['_controller' => 'App\Controller\TutorialsController:removeCategoryAction']));
$collection->add('tutorials.update-category', new Route('/tutorials/edit-category', ['_controller' => 'App\Controller\TutorialsController:updateCategoryAction']));
$collection->add('tutorials.upload-file', new Route('/tutorials/upload-file', ['_controller' => 'App\Controller\TutorialsController:uploadFileAction']));
$collection->add('tutorials.get-tutorial', new Route('/tutorials/get/{tutorialId}', ['_controller' => 'App\Controller\TutorialsController:getTutorialAction']));
$collection->add('tutorials.upload-thumb-file', new Route('/tutorials/upload-thumb-file', ['_controller' => 'App\Controller\TutorialsController:uploadThumbFileAction']));
$collection->add('tutorials.update', new Route('/tutorials/update', ['_controller' => 'App\Controller\TutorialsController:updateTutorialAction']));
$collection->add('tutorials.download', new Route('/tutorials/download/{tutorialId}', ['_controller' => 'App\Controller\TutorialsController:downloadFileAction']));
$collection->add('tutorials.remove', new Route('/tutorials/remove', ['_controller' => 'App\Controller\TutorialsController:removeAction']));
$collection->add('tutorials.index', new Route('/tutorials/index', ['_controller' => 'App\Controller\TutorialsController:indexAction']));

/** Shared forms */

$collection->add('shared-forms.get-by-uid', new Route('/shared-forms/get/{uid}', ['_controller' => 'App\Controller\SharedFormsController:getByUidAction']));
$collection->add('shared-forms.send-to-participant', new Route('/shared-forms/send-to-participant', ['_controller' => 'App\Controller\SharedFormsController:sendToParticipantAction']));
$collection->add('shared-forms.submit', new Route('/shared-forms/submit', ['_controller' => 'App\Controller\SharedFormsController:submitAction']));
$collection->add('shared-forms.download-pdf', new Route('/shared-forms/download-submission', ['_controller' => 'App\Controller\SharedFormsController:downloadPdfAction']));


return $collection;
