<?php

declare(strict_types=1);

namespace App\Application\Automation;

use App\Domain\Automation\Enums\ActionType;
use App\Domain\Automation\Enums\TriggerType;

/**
 * TemplateCatalog - 12 templates pre-armados de workflows.
 *
 * Cada template es un graph JSON listo para clonarse a un tenant.
 */
class TemplateCatalog
{
    /**
     * @return array<int, array{name: string, slug: string, description: string, category: string, graph: array}>
     */
    public function all(): array
    {
        return [
            [
                'name' => 'Welcome new contact',
                'slug' => 'welcome-new-contact',
                'description' => 'Envía un email de bienvenida cuando se crea un nuevo contacto.',
                'category' => 'engagement',
                'graph' => [
                    'nodes' => [
                        ['id' => 't1', 'type' => 'trigger', 'trigger_type' => TriggerType::RecordCreated->value, 'config' => ['entity' => 'contact']],
                        ['id' => 'a1', 'type' => 'action', 'action_type' => ActionType::SendEmail->value, 'config' => ['template' => 'welcome', 'to' => '{{contact.email}}']],
                    ],
                    'edges' => [['from' => 't1', 'to' => 'a1']],
                ],
            ],
            [
                'name' => 'Deal stage move ? email',
                'slug' => 'deal-stage-email',
                'description' => 'Notifica al cliente cuando un deal cambia de etapa.',
                'category' => 'sales',
                'graph' => [
                    'nodes' => [
                        ['id' => 't1', 'type' => 'trigger', 'trigger_type' => TriggerType::RecordUpdated->value, 'config' => ['entity' => 'deal', 'watch_field' => 'stage_id']],
                        ['id' => 'a1', 'type' => 'action', 'action_type' => ActionType::SendEmail->value, 'config' => ['template' => 'deal_stage_change', 'to' => '{{contact.email}}']],
                    ],
                    'edges' => [['from' => 't1', 'to' => 'a1']],
                ],
            ],
            [
                'name' => 'Order paid ? fulfillment',
                'slug' => 'order-paid-fulfillment',
                'description' => 'Cuando una orden es pagada, actualiza el status y notifica al cliente por WhatsApp.',
                'category' => 'operations',
                'graph' => [
                    'nodes' => [
                        ['id' => 't1', 'type' => 'trigger', 'trigger_type' => TriggerType::RecordUpdated->value, 'config' => ['entity' => 'order', 'watch_field' => 'status', 'to_value' => 'paid']],
                        ['id' => 'a1', 'type' => 'action', 'action_type' => ActionType::UpdateRecord->value, 'config' => ['entity' => 'order', 'set' => ['fulfillment_status' => 'pending']]],
                        ['id' => 'a2', 'type' => 'action', 'action_type' => ActionType::SendWhatsApp->value, 'config' => ['template' => 'order_paid', 'to' => '{{order.customer.phone}}']],
                    ],
                    'edges' => [
                        ['from' => 't1', 'to' => 'a1'],
                        ['from' => 'a1', 'to' => 'a2'],
                    ],
                ],
            ],
            [
                'name' => 'Subscription renewal reminder',
                'slug' => 'subscription-renewal-reminder',
                'description' => 'Recordatorio automático 7 días antes de la renovación.',
                'category' => 'billing',
                'graph' => [
                    'nodes' => [
                        ['id' => 't1', 'type' => 'trigger', 'trigger_type' => TriggerType::ScheduleReached->value, 'config' => ['cron' => '0 9 * * *', 'days_before_renewal' => 7]],
                        ['id' => 'a1', 'type' => 'action', 'action_type' => ActionType::SendEmail->value, 'config' => ['template' => 'renewal_reminder']],
                    ],
                    'edges' => [['from' => 't1', 'to' => 'a1']],
                ],
            ],
            [
                'name' => 'Webhook received ? AI action',
                'slug' => 'webhook-ai-action',
                'description' => 'Procesa webhooks entrantes con AI y crea registros.',
                'category' => 'integration',
                'graph' => [
                    'nodes' => [
                        ['id' => 't1', 'type' => 'trigger', 'trigger_type' => TriggerType::WebhookReceived->value, 'config' => ['endpoint' => '/webhooks/inbound']],
                        ['id' => 'a1', 'type' => 'action', 'action_type' => ActionType::RunAiCommand->value, 'config' => ['assistant' => 'sentiment', 'input' => '{{webhook.payload.body}}']],
                        ['id' => 'a2', 'type' => 'action', 'action_type' => ActionType::CreateRecord->value, 'config' => ['entity' => 'interaction']],
                    ],
                    'edges' => [
                        ['from' => 't1', 'to' => 'a1'],
                        ['from' => 'a1', 'to' => 'a2'],
                    ],
                ],
            ],
            [
                'name' => 'Manual approval flow',
                'slug' => 'manual-approval',
                'description' => 'Workflow que requiere aprobación manual antes de ejecutar.',
                'category' => 'governance',
                'graph' => [
                    'nodes' => [
                        ['id' => 't1', 'type' => 'trigger', 'trigger_type' => TriggerType::ManualRun->value, 'config' => []],
                        ['id' => 'a1', 'type' => 'action', 'action_type' => ActionType::WaitForApproval->value, 'config' => ['approver_role' => 'manager', 'timeout_hours' => 24]],
                        ['id' => 'a2', 'type' => 'action', 'action_type' => ActionType::CreateRecord->value, 'config' => ['entity' => 'task']],
                    ],
                    'edges' => [
                        ['from' => 't1', 'to' => 'a1'],
                        ['from' => 'a1', 'to' => 'a2'],
                    ],
                ],
            ],
            [
                'name' => 'Branching by deal value',
                'slug' => 'branch-by-deal-value',
                'description' => 'Rama el workflow según el valor del deal.',
                'category' => 'sales',
                'graph' => [
                    'nodes' => [
                        ['id' => 't1', 'type' => 'trigger', 'trigger_type' => TriggerType::RecordCreated->value, 'config' => ['entity' => 'deal']],
                        ['id' => 'a1', 'type' => 'action', 'action_type' => ActionType::Branch->value, 'config' => [
                            'conditions' => [
                                ['if' => 'value > 10000', 'then_node' => 'a2'],
                                ['else_node' => 'a3'],
                            ],
                        ]],
                        ['id' => 'a2', 'type' => 'action', 'action_type' => ActionType::SendEmail->value, 'config' => ['template' => 'high_value_alert', 'to' => 'sales-lead@example.com']],
                        ['id' => 'a3', 'type' => 'action', 'action_type' => ActionType::SendEmail->value, 'config' => ['template' => 'standard_welcome']],
                    ],
                    'edges' => [
                        ['from' => 't1', 'to' => 'a1'],
                        ['from' => 'a1', 'to' => 'a2'],
                        ['from' => 'a1', 'to' => 'a3'],
                    ],
                ],
            ],
            [
                'name' => 'High-value deal escalation',
                'slug' => 'high-value-escalation',
                'description' => 'Crea una tarea urgente para deals > 10K.',
                'category' => 'sales',
                'graph' => [
                    'nodes' => [
                        ['id' => 't1', 'type' => 'trigger', 'trigger_type' => TriggerType::RecordCreated->value, 'config' => ['entity' => 'deal']],
                        ['id' => 'a1', 'type' => 'action', 'action_type' => ActionType::CreateRecord->value, 'config' => ['entity' => 'task', 'priority' => 'high', 'assignee_role' => 'sales_manager']],
                    ],
                    'edges' => [['from' => 't1', 'to' => 'a1']],
                ],
            ],
            [
                'name' => 'Loop over contacts',
                'slug' => 'loop-over-contacts',
                'description' => 'Envía emails programados a una lista de contactos.',
                'category' => 'engagement',
                'graph' => [
                    'nodes' => [
                        ['id' => 't1', 'type' => 'trigger', 'trigger_type' => TriggerType::ScheduleReached->value, 'config' => ['cron' => '0 10 * * *']],
                        ['id' => 'a1', 'type' => 'action', 'action_type' => ActionType::Loop->value, 'config' => ['iterate_over' => 'contacts', 'where' => ['lifecycle_stage' => 'qualified']]],
                        ['id' => 'a2', 'type' => 'action', 'action_type' => ActionType::SendEmail->value, 'config' => ['template' => 'weekly_digest']],
                    ],
                    'edges' => [
                        ['from' => 't1', 'to' => 'a1'],
                        ['from' => 'a1', 'to' => 'a2'],
                    ],
                ],
            ],
            [
                'name' => 'AI draft reply on inbound',
                'slug' => 'ai-draft-reply',
                'description' => 'Genera un draft de respuesta con AI ante mensajes entrantes.',
                'category' => 'ai',
                'graph' => [
                    'nodes' => [
                        ['id' => 't1', 'type' => 'trigger', 'trigger_type' => TriggerType::AiMessageReceived->value, 'config' => []],
                        ['id' => 'a1', 'type' => 'action', 'action_type' => ActionType::RunAiCommand->value, 'config' => ['assistant' => 'email_reply', 'input' => '{{message.body}}']],
                        ['id' => 'a2', 'type' => 'action', 'action_type' => ActionType::SendEmail->value, 'config' => ['template' => 'ai_draft', 'draft_only' => true]],
                    ],
                    'edges' => [
                        ['from' => 't1', 'to' => 'a1'],
                        ['from' => 'a1', 'to' => 'a2'],
                    ],
                ],
            ],
            [
                'name' => 'Lost deal follow-up',
                'slug' => 'lost-deal-followup',
                'description' => 'Cuando un deal se marca como perdido, crea tarea de seguimiento.',
                'category' => 'sales',
                'graph' => [
                    'nodes' => [
                        ['id' => 't1', 'type' => 'trigger', 'trigger_type' => TriggerType::RecordUpdated->value, 'config' => ['entity' => 'deal', 'watch_field' => 'status', 'to_value' => 'lost']],
                        ['id' => 'a1', 'type' => 'action', 'action_type' => ActionType::CreateRecord->value, 'config' => ['entity' => 'task', 'due_days' => 30, 'subject' => 'Follow-up deal perdido']],
                        ['id' => 'a2', 'type' => 'action', 'action_type' => ActionType::SendEmail->value, 'config' => ['template' => 'feedback_request']],
                    ],
                    'edges' => [
                        ['from' => 't1', 'to' => 'a1'],
                        ['from' => 'a1', 'to' => 'a2'],
                    ],
                ],
            ],
            [
                'name' => 'NPS score collection',
                'slug' => 'nps-collection',
                'description' => 'Envía encuesta NPS a clientes activos cada 90 días.',
                'category' => 'feedback',
                'graph' => [
                    'nodes' => [
                        ['id' => 't1', 'type' => 'trigger', 'trigger_type' => TriggerType::ScheduleReached->value, 'config' => ['cron' => '0 14 1 */3 *']],
                        ['id' => 'a1', 'type' => 'action', 'action_type' => ActionType::SendEmail->value, 'config' => ['template' => 'nps_survey', 'include_survey_link' => true]],
                    ],
                    'edges' => [['from' => 't1', 'to' => 'a1']],
                ],
            ],
        ];
    }

    public function findBySlug(string $slug): ?array
    {
        foreach ($this->all() as $template) {
            if ($template['slug'] === $slug) {
                return $template;
            }
        }

        return null;
    }
}
