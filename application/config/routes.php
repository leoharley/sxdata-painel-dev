<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$route['default_controller'] = 'dashboard';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// Admin routes
$route['admin'] = 'dashboard';
$route['admin/login'] = 'auth/login';
$route['admin/logout'] = 'auth/logout';
$route['admin/dashboard'] = 'dashboard';
$route['admin/questionnaires'] = 'questionnaires';
$route['admin/questionnaires/create'] = 'questionnaires/create';
$route['admin/questionnaires/edit/(:num)'] = 'questionnaires/edit/$1';
$route['admin/questionnaires/duplicate/(:num)'] = 'questionnaires/duplicate/$1';
$route['admin/responses'] = 'responses';
$route['admin/responses/view/(:num)'] = 'responses/view/$1';
$route['admin/responses/export'] = 'responses/export';
$route['admin/users'] = 'users';
$route['admin/reports'] = 'reports';
$route['admin/settings'] = 'settings';
$route['politica_de_privacidade'] = 'welcome/politica_de_privacidade';

// Rotas do Módulo de IA
$route['admin/ai'] = 'ai';
$route['admin/ai/settings'] = 'ai/settings';
$route['admin/ai/security'] = 'ai/security';
$route['ai/security'] = 'ai/security';
$route['admin/ai/directives'] = 'ai/directives';
$route['ai/save_directive'] = 'ai/save_directive';
$route['ai/get_directive'] = 'ai/get_directive';
$route['ai/toggle_directive'] = 'ai/toggle_directive';
$route['ai/delete_directive'] = 'ai/delete_directive';
$route['ai/clear_directives'] = 'ai/clear_directives';
$route['admin/ai/prompts'] = 'ai/prompts';
$route['admin/ai/logs'] = 'ai/logs';
$route['admin/ai/transcriptions'] = 'ai/transcriptions';
$route['admin/ai/inconsistencies'] = 'ai/inconsistencies';
$route['ai/clear_inconsistencies'] = 'ai/clear_inconsistencies';
$route['ai/clear_followups'] = 'ai/clear_followups';
$route['ai/get_question_detail'] = 'ai/get_question_detail';
$route['ai/get_report_data'] = 'ai/get_report_data';
$route['ai/update_report'] = 'ai/update_report';

// Client routes
$route['client/dashboard'] = 'client/dashboard';
$route['client/view_analysis/(:num)'] = 'client/view_analysis/$1';
$route['client/generate_chart_description'] = 'client/generate_chart_description';
$route['admin/ai/corrections'] = 'ai/corrections';
$route['admin/ai/smart_fill'] = 'ai/smart_fill';
$route['ai/approve_suggestion'] = 'ai/approve_suggestion';
$route['ai/reject_suggestion'] = 'ai/reject_suggestion';
$route['ai/approve_reformulation'] = 'ai/approve_reformulation';
$route['ai/generate_reformulations_batch'] = 'ai/generate_reformulations_batch';
$route['admin/ai/reformulations'] = 'ai/reformulations';
$route['admin/ai/adaptive'] = 'ai/adaptive';
$route['ai/generate_adaptive_rules'] = 'ai/generate_adaptive_rules';
$route['ai/clear_adaptive_rules'] = 'ai/clear_adaptive_rules';
$route['ai/approve_adaptive_rule'] = 'ai/approve_adaptive_rule';
$route['ai/reject_adaptive_rule'] = 'ai/reject_adaptive_rule';
$route['ai/restore_adaptive_rule'] = 'ai/restore_adaptive_rule';
$route['admin/ai/followup'] = 'ai/followup';
$route['admin/ai/analysis'] = 'ai/analysis';
$route['admin/ai/view_analysis/(:num)'] = 'ai/view_analysis/$1';
$route['ai/delete_analysis'] = 'ai/delete_analysis';
$route['ai/clear_corrections'] = 'ai/clear_corrections';
$route['admin/ai/charts'] = 'ai/charts';
$route['admin/ai/reports'] = 'ai/reports';
$route['admin/ai/view_report/(:num)'] = 'ai/view_report/$1';

// API routes
$route['api/auth/login'] = 'api/auth/login';
$route['api/auth/verify'] = 'api/auth/verify';
$route['api/questionnaires'] = 'api/questionnaires/index';
$route['api/forms/submit'] = 'api/forms/submit';
$route['api/photos/upload'] = 'api/photos/upload';
$route['api/audios/upload'] = 'api/audios/upload';

// Pontos de campo do mapa integrado (docs/PLANO_MAPA_INTEGRADO.md)
$route['api/points'] = 'api/points/index';
$route['api/points/submit'] = 'api/points/submit';

// Rotas para API de Estatísticas
$route['api/stats/user/(:num)'] = 'api/stats/user/$1';
$route['api/stats/user'] = 'api/stats/user';
$route['api/stats/overview'] = 'api/stats/overview';

// Novas rotas para Supervisores/Administradores
$route['api/stats/applicators'] = 'api/stats/applicators';
$route['api/stats/locations'] = 'api/stats/locations';

$route['api/stats/history'] = 'api/stats/history';
$route['api/stats/history/(:num)'] = 'api/stats/history/$1';
$route['api/stats/history/(:num)/summary'] = 'api/stats/history_summary/$1';
$route['api/stats/history/summary'] = 'api/stats/history_summary';

$route['api/stats/questionnaires-analysis'] = 'api/stats/questionnaires_analysis';
$route['api/stats/questionnaire-analysis/(:num)'] = 'api/stats/questionnaire_analysis/$1';
$route['api/stats/questions-analysis'] = 'api/stats/questions_analysis';
$route['api/stats/applicators_app'] = 'api/stats/applicators_app';


$route['api/questionnaires/(:num)/questions'] = 'api/responses/questionnaire_questions/$1';
$route['api/responses/raw-data'] = 'api/responses/raw_data';
$route['api/responses/count'] = 'api/responses/count_records';
$route['api/responses/validate-export'] = 'api/responses/validate_export';
$route['api/responses/export-preview'] = 'api/responses/export_preview';
$route['api/responses/export-statistics'] = 'api/responses/export_statistics';
$route['api/responses/log-export'] = 'api/responses/log_export';
$route['api/responses/export-limits'] = 'api/responses/export_limits';
$route['api/responses/export-history'] = 'api/responses/export_history';


$route['responses/export_raw_data'] = 'responses/export_raw_data';

// Rotas adicionais úteis para a funcionalidade
$route['responses/preview_export'] = 'responses/preview_export';
$route['responses/validate_export'] = 'responses/validate_export';
$route['responses/export_status/(:num)'] = 'responses/export_status/$1';

// Rotas para AJAX calls
$route['api/responses/count'] = 'responses/ajax_count_responses';
$route['api/responses/preview'] = 'responses/ajax_preview_export';

$route['api/health'] = 'api/system/health';

// API de IA (preparação para app móvel)
$route['api/ai/status'] = 'api/ai/status';
$route['api/ai/transcribe'] = 'api/ai/transcribe';
$route['api/ai/followup-tips'] = 'api/ai/followup_tips';
$route['ai/action_followup'] = 'ai/action_followup';
$route['ai/edit_followup'] = 'ai/edit_followup';
$route['api/ai/transcriptions/sync'] = 'api/ai/transcriptions_sync';
$route['api/ai/check-inconsistencies'] = 'api/ai/check_inconsistencies';
$route['api/ai/suggest-fill'] = 'api/ai/suggest_fill';
$route['api/ai/standardize'] = 'api/ai/standardize';
$route['api/ai/smart-suggestions'] = 'api/ai/smart_suggestions';
$route['api/ai/reformulate-questions'] = 'api/ai/reformulate_questions';
$route['api/ai/adaptive-next'] = 'api/ai/adaptive_next';
$route['api/ai/follow-up'] = 'api/ai/follow_up';
$route['api/ai/generate-report'] = 'api/ai/generate_report';
$route['api/ai/audit-log'] = 'api/ai/audit_log';
$route['api/ai/audit-log/batch'] = 'api/ai/audit_log_batch';
$route['api/ai/approved-suggestions'] = 'api/ai/approved_suggestions';
// Coleta pública por QR Code (sem login)
$route['responder/([a-f0-9]{32})/obrigado'] = 'responder/obrigado/$1';
$route['responder/([a-f0-9]{32})'] = 'responder/index/$1';

// Relatórios municipais automatizados
$route['relatorios-automatizados'] = 'relatorios_municipais/index';
$route['relatorios-automatizados/(.+)'] = 'relatorios_municipais/$1';
