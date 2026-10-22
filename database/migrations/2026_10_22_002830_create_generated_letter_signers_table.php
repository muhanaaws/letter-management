<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Run the migrations. */
    public function up(): void
    {
        Schema::create('generated_letter_signers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('generated_letter_id')
                ->constrained('generated_letters')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Snapshot of the signer identity recorded on the issued letter.
            $table->string('approver_name');
            $table->string('approver_role')->nullable();
            $table->string('approver_nip')->nullable();
            $table->string('approver_department')->nullable();

            // Physical placement of the signer QR block on the letter.
            $table->string('qr_position', 10)->default('right'); // left | center | right
            $table->unsignedSmallInteger('qr_row')->default(0);

            // Each signer gets a unique verification token.
            $table->string('verification_token', 64)->unique();

            // Actual approval timestamp from the submission log.
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
        });
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::dropIfExists('generated_letter_signers');
    }
};
