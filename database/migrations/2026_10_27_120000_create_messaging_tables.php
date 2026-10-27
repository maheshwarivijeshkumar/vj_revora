<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email accounts, templates, follow-up sequences and the conversation log
 * shared by email and WhatsApp (§15, §25, §26, §88).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // smtp (SMTP + IMAP), google (Gmail API) or microsoft (Graph).
            $table->string('provider');
            $table->string('email');
            $table->string('from_name')->nullable();
            $table->string('status')->default('connected')->index();
            // Passwords or OAuth tokens, encrypted by the model cast (§11).
            $table->text('credentials');
            $table->boolean('is_default')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'email']);
        });

        Schema::create('whatsapp_accounts', function (Blueprint $table): void {
            $table->id();
            // One WhatsApp number per workspace.
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            // Globally unique: the webhook finds the workspace by it.
            $table->string('phone_number_id')->unique();
            $table->string('business_account_id')->nullable();
            $table->string('display_phone_number')->nullable();
            $table->text('access_token');
            // The approved template sent to each new lead, and its language.
            $table->string('welcome_template')->nullable();
            $table->string('template_language')->default('en');
            $table->boolean('auto_send_welcome')->default(true);
            $table->string('status')->default('connected');
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'name']);
        });

        Schema::create('follow_up_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            // Minutes after the first email with no reply before the lead is
            // scored 0/10 and the sequence stops.
            $table->unsignedInteger('no_reply_after_minutes')->default(4320);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('follow_up_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('follow_up_sequence_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            // Minutes after enrollment, so the schedule reads as "at 0h, 6h,
            // 24h" however late an earlier step went out.
            $table->unsignedInteger('delay_minutes');
            $table->foreignId('email_template_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['follow_up_sequence_id', 'position']);
            $table->index(['tenant_id', 'follow_up_sequence_id']);
        });

        Schema::create('follow_up_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('follow_up_sequence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_account_id')->nullable()->constrained()->nullOnDelete();
            // active, replied, no_reply, stopped, failed
            $table->string('status')->default('active');
            $table->unsignedSmallInteger('next_position')->default(1);
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'lead_id', 'follow_up_sequence_id'], 'enrollments_lead_sequence_unique');
            $table->index(['status', 'next_run_at']);
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('direction');
            $table->foreignId('email_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('follow_up_enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('step_position')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->string('from_address')->nullable();
            $table->string('to_address')->nullable();
            // Message-ID for email, wamid for WhatsApp. Unique per channel so
            // a mailbox re-read or a replayed webhook is a no-op (§58).
            $table->string('provider_message_id')->nullable();
            $table->string('in_reply_to')->nullable();
            $table->string('status');
            $table->text('error')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'channel', 'provider_message_id'], 'messages_provider_unique');
            $table->index(['tenant_id', 'lead_id', 'created_at']);
        });

        Schema::table('leads', function (Blueprint $table): void {
            // How the lead responded to follow-up, out of 10. Separate from
            // the 0–100 fit score, which says who they are, not how they
            // answered.
            $table->unsignedTinyInteger('engagement_score')->nullable();
            $table->string('engagement_reason', 500)->nullable();
            $table->timestamp('engagement_scored_at')->nullable();
            // Per channel, because "stop emailing me" is not "stop
            // WhatsApping me" (§88).
            $table->timestamp('email_opted_out_at')->nullable();
            $table->timestamp('whatsapp_opted_out_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn([
                'engagement_score', 'engagement_reason', 'engagement_scored_at',
                'email_opted_out_at', 'whatsapp_opted_out_at',
            ]);
        });

        Schema::dropIfExists('messages');
        Schema::dropIfExists('follow_up_enrollments');
        Schema::dropIfExists('follow_up_steps');
        Schema::dropIfExists('follow_up_sequences');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('whatsapp_accounts');
        Schema::dropIfExists('email_accounts');
    }
};
