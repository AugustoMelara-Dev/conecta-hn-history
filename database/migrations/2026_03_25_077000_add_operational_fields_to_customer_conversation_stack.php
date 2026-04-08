<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->upCustomerConversations();
        $this->upCustomerTasks();
        $this->upCustomerConversationMessages();
        $this->upCustomerJourneyRuns();
    }

    public function down(): void
    {
        $this->downCustomerJourneyRuns();
        $this->downCustomerConversationMessages();
        $this->downCustomerTasks();
        $this->downCustomerConversations();
    }

    private function upCustomerConversations(): void
    {
        if (! Schema::hasTable('customer_conversations')) {
            return;
        }

        if (! Schema::hasColumn('customer_conversations', 'assignment_group')) {
            Schema::table('customer_conversations', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_conversations', 'location_id')) {
                    $table->string('assignment_group', 32)->nullable()->after('location_id');
                } else {
                    $table->string('assignment_group', 32)->nullable();
                }
            });
        }

        if (! Schema::hasColumn('customer_conversations', 'response_sla_minutes')) {
            Schema::table('customer_conversations', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_conversations', 'priority')) {
                    $table->unsignedSmallInteger('response_sla_minutes')->nullable()->after('priority');
                } else {
                    $table->unsignedSmallInteger('response_sla_minutes')->nullable();
                }
            });
        }

        if (! Schema::hasColumn('customer_conversations', 'escalation_due_at')) {
            Schema::table('customer_conversations', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_conversations', 'response_due_at')) {
                    $table->timestamp('escalation_due_at')->nullable()->after('response_due_at');
                } else {
                    $table->timestamp('escalation_due_at')->nullable();
                }
            });
        }

        if (
            Schema::hasColumn('customer_conversations', 'tenant_id')
            && Schema::hasColumn('customer_conversations', 'assignment_group')
            && Schema::hasColumn('customer_conversations', 'status')
            && ! $this->indexExists('customer_conversations', 'customer_conversations_tenant_group_status_idx')
        ) {
            Schema::table('customer_conversations', function (Blueprint $table): void {
                $table->index(['tenant_id', 'assignment_group', 'status'], 'customer_conversations_tenant_group_status_idx');
            });
        }

        if (
            Schema::hasColumn('customer_conversations', 'tenant_id')
            && Schema::hasColumn('customer_conversations', 'escalation_due_at')
            && ! $this->indexExists('customer_conversations', 'customer_conversations_tenant_escalation_idx')
        ) {
            Schema::table('customer_conversations', function (Blueprint $table): void {
                $table->index(['tenant_id', 'escalation_due_at'], 'customer_conversations_tenant_escalation_idx');
            });
        }
    }

    private function upCustomerTasks(): void
    {
        if (! Schema::hasTable('customer_tasks')) {
            return;
        }

        if (! Schema::hasColumn('customer_tasks', 'assignment_group')) {
            Schema::table('customer_tasks', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_tasks', 'location_id')) {
                    $table->string('assignment_group', 32)->nullable()->after('location_id');
                } else {
                    $table->string('assignment_group', 32)->nullable();
                }
            });
        }

        if (! Schema::hasColumn('customer_tasks', 'response_sla_minutes')) {
            Schema::table('customer_tasks', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_tasks', 'priority')) {
                    $table->unsignedSmallInteger('response_sla_minutes')->nullable()->after('priority');
                } else {
                    $table->unsignedSmallInteger('response_sla_minutes')->nullable();
                }
            });
        }

        if (
            Schema::hasColumn('customer_tasks', 'tenant_id')
            && Schema::hasColumn('customer_tasks', 'assignment_group')
            && Schema::hasColumn('customer_tasks', 'status')
            && ! $this->indexExists('customer_tasks', 'customer_tasks_tenant_group_status_idx')
        ) {
            Schema::table('customer_tasks', function (Blueprint $table): void {
                $table->index(['tenant_id', 'assignment_group', 'status'], 'customer_tasks_tenant_group_status_idx');
            });
        }
    }

    private function upCustomerConversationMessages(): void
    {
        if (! Schema::hasTable('customer_conversation_messages')) {
            return;
        }

        if (! Schema::hasColumn('customer_conversation_messages', 'provider_message_id')) {
            Schema::table('customer_conversation_messages', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_conversation_messages', 'status')) {
                    $table->string('provider_message_id', 160)->nullable()->after('status');
                } else {
                    $table->string('provider_message_id', 160)->nullable();
                }
            });
        }

        if (! Schema::hasColumn('customer_conversation_messages', 'provider_thread_key')) {
            Schema::table('customer_conversation_messages', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_conversation_messages', 'provider_message_id')) {
                    $table->string('provider_thread_key', 160)->nullable()->after('provider_message_id');
                } else {
                    $table->string('provider_thread_key', 160)->nullable();
                }
            });
        }

        if (! Schema::hasColumn('customer_conversation_messages', 'provider_reply_to')) {
            Schema::table('customer_conversation_messages', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_conversation_messages', 'provider_thread_key')) {
                    $table->string('provider_reply_to', 160)->nullable()->after('provider_thread_key');
                } else {
                    $table->string('provider_reply_to', 160)->nullable();
                }
            });
        }

        if (
            Schema::hasColumn('customer_conversation_messages', 'tenant_id')
            && Schema::hasColumn('customer_conversation_messages', 'provider_thread_key')
            && ! $this->indexExists('customer_conversation_messages', 'customer_conversation_messages_tenant_thread_idx')
        ) {
            Schema::table('customer_conversation_messages', function (Blueprint $table): void {
                $table->index(['tenant_id', 'provider_thread_key'], 'customer_conversation_messages_tenant_thread_idx');
            });
        }

        if (
            Schema::hasColumn('customer_conversation_messages', 'tenant_id')
            && Schema::hasColumn('customer_conversation_messages', 'provider_message_id')
            && ! $this->indexExists('customer_conversation_messages', 'customer_conversation_messages_tenant_provider_msg_idx')
        ) {
            Schema::table('customer_conversation_messages', function (Blueprint $table): void {
                $table->index(['tenant_id', 'provider_message_id'], 'customer_conversation_messages_tenant_provider_msg_idx');
            });
        }
    }

    private function upCustomerJourneyRuns(): void
    {
        if (! Schema::hasTable('customer_journey_runs')) {
            return;
        }

        if (! Schema::hasColumn('customer_journey_runs', 'current_step_executed_at')) {
            Schema::table('customer_journey_runs', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_journey_runs', 'step_due_at')) {
                    $table->timestamp('current_step_executed_at')->nullable()->after('step_due_at');
                } else {
                    $table->timestamp('current_step_executed_at')->nullable();
                }
            });
        }

        if (! Schema::hasColumn('customer_journey_runs', 'auto_step_count')) {
            Schema::table('customer_journey_runs', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_journey_runs', 'current_step_executed_at')) {
                    $table->unsignedSmallInteger('auto_step_count')->default(0)->after('current_step_executed_at');
                } else {
                    $table->unsignedSmallInteger('auto_step_count')->default(0);
                }
            });
        }

        if (! Schema::hasColumn('customer_journey_runs', 'last_auto_touchpoint_id')) {
            Schema::table('customer_journey_runs', function (Blueprint $table): void {
                if (Schema::hasColumn('customer_journey_runs', 'auto_step_count')) {
                    $table->foreignId('last_auto_touchpoint_id')
                        ->nullable()
                        ->after('auto_step_count')
                        ->constrained('customer_touchpoints')
                        ->nullOnDelete();
                } else {
                    $table->foreignId('last_auto_touchpoint_id')
                        ->nullable()
                        ->constrained('customer_touchpoints')
                        ->nullOnDelete();
                }
            });
        }

        if (
            Schema::hasColumn('customer_journey_runs', 'tenant_id')
            && Schema::hasColumn('customer_journey_runs', 'sequence_state')
            && Schema::hasColumn('customer_journey_runs', 'step_due_at')
            && ! $this->indexExists('customer_journey_runs', 'customer_journey_runs_tenant_sequence_step_due_idx')
        ) {
            Schema::table('customer_journey_runs', function (Blueprint $table): void {
                $table->index(['tenant_id', 'sequence_state', 'step_due_at'], 'customer_journey_runs_tenant_sequence_step_due_idx');
            });
        }
    }

    private function downCustomerJourneyRuns(): void
    {
        if (! Schema::hasTable('customer_journey_runs')) {
            return;
        }

        Schema::table('customer_journey_runs', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_journey_runs', 'last_auto_touchpoint_id')) {
                $table->dropConstrainedForeignId('last_auto_touchpoint_id');
            }
            if ($this->indexExists('customer_journey_runs', 'customer_journey_runs_tenant_sequence_step_due_idx')) {
                $table->dropIndex('customer_journey_runs_tenant_sequence_step_due_idx');
            }

            $dropColumns = array_values(array_filter([
                Schema::hasColumn('customer_journey_runs', 'current_step_executed_at') ? 'current_step_executed_at' : null,
                Schema::hasColumn('customer_journey_runs', 'auto_step_count') ? 'auto_step_count' : null,
            ]));
            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }

    private function downCustomerConversationMessages(): void
    {
        if (! Schema::hasTable('customer_conversation_messages')) {
            return;
        }

        Schema::table('customer_conversation_messages', function (Blueprint $table): void {
            if ($this->indexExists('customer_conversation_messages', 'customer_conversation_messages_tenant_thread_idx')) {
                $table->dropIndex('customer_conversation_messages_tenant_thread_idx');
            }
            if ($this->indexExists('customer_conversation_messages', 'customer_conversation_messages_tenant_provider_msg_idx')) {
                $table->dropIndex('customer_conversation_messages_tenant_provider_msg_idx');
            }

            $dropColumns = array_values(array_filter([
                Schema::hasColumn('customer_conversation_messages', 'provider_message_id') ? 'provider_message_id' : null,
                Schema::hasColumn('customer_conversation_messages', 'provider_thread_key') ? 'provider_thread_key' : null,
                Schema::hasColumn('customer_conversation_messages', 'provider_reply_to') ? 'provider_reply_to' : null,
            ]));
            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }

    private function downCustomerTasks(): void
    {
        if (! Schema::hasTable('customer_tasks')) {
            return;
        }

        Schema::table('customer_tasks', function (Blueprint $table): void {
            if ($this->indexExists('customer_tasks', 'customer_tasks_tenant_group_status_idx')) {
                $table->dropIndex('customer_tasks_tenant_group_status_idx');
            }

            $dropColumns = array_values(array_filter([
                Schema::hasColumn('customer_tasks', 'assignment_group') ? 'assignment_group' : null,
                Schema::hasColumn('customer_tasks', 'response_sla_minutes') ? 'response_sla_minutes' : null,
            ]));
            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }

    private function downCustomerConversations(): void
    {
        if (! Schema::hasTable('customer_conversations')) {
            return;
        }

        Schema::table('customer_conversations', function (Blueprint $table): void {
            if ($this->indexExists('customer_conversations', 'customer_conversations_tenant_group_status_idx')) {
                $table->dropIndex('customer_conversations_tenant_group_status_idx');
            }
            if ($this->indexExists('customer_conversations', 'customer_conversations_tenant_escalation_idx')) {
                $table->dropIndex('customer_conversations_tenant_escalation_idx');
            }

            $dropColumns = array_values(array_filter([
                Schema::hasColumn('customer_conversations', 'assignment_group') ? 'assignment_group' : null,
                Schema::hasColumn('customer_conversations', 'response_sla_minutes') ? 'response_sla_minutes' : null,
                Schema::hasColumn('customer_conversations', 'escalation_due_at') ? 'escalation_due_at' : null,
            ]));
            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

        return $result !== [];
    }
};
