<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_messages')) {
            return;
        }

        Schema::table('email_messages', function (Blueprint $t) {
            if (! Schema::hasColumn('email_messages', 'attempts')) {
                $t->unsignedSmallInteger('attempts')->default(0)->after('status');
            }
            if (! Schema::hasColumn('email_messages', 'max_attempts')) {
                $t->unsignedSmallInteger('max_attempts')->default(5)->after('attempts');
            }
            if (! Schema::hasColumn('email_messages', 'next_attempt_at')) {
                $t->timestamp('next_attempt_at')->nullable()->after('max_attempts')->index();
            }
            if (! Schema::hasColumn('email_messages', 'locked_at')) {
                $t->timestamp('locked_at')->nullable()->after('next_attempt_at')->index();
            }
            if (! Schema::hasColumn('email_messages', 'locked_by')) {
                $t->string('locked_by', 80)->nullable()->after('locked_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_messages')) {
            return;
        }

        Schema::table('email_messages', function (Blueprint $t) {
            if (Schema::hasColumn('email_messages', 'locked_by')) {
                $t->dropColumn('locked_by');
            }
            if (Schema::hasColumn('email_messages', 'locked_at')) {
                $t->dropColumn('locked_at');
            }
            if (Schema::hasColumn('email_messages', 'next_attempt_at')) {
                $t->dropColumn('next_attempt_at');
            }
            if (Schema::hasColumn('email_messages', 'max_attempts')) {
                $t->dropColumn('max_attempts');
            }
            if (Schema::hasColumn('email_messages', 'attempts')) {
                $t->dropColumn('attempts');
            }
        });
    }
};

