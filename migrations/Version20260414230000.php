<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adaptive MFA — Add performance indexes for login_attempts, user_sessions, and otp_codes.
 */
final class Version20260414230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes for Adaptive MFA performance: login_attempts, user_sessions, otp_codes';
    }

    public function up(Schema $schema): void
    {
        // Index for brute-force detection: count recent failed attempts
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_login_attempts_user_time ON login_attempts (user_id, login_time)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_login_attempts_user_success ON login_attempts (user_id, success)');

        // Index for active sessions lookup
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_user_sessions_user_active ON user_sessions (user_id, is_active)');

        // Index for OTP validation
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_otp_codes_user_valid ON otp_codes (user_id, used, expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_login_attempts_user_time ON login_attempts');
        $this->addSql('DROP INDEX IF EXISTS idx_login_attempts_user_success ON login_attempts');
        $this->addSql('DROP INDEX IF EXISTS idx_user_sessions_user_active ON user_sessions');
        $this->addSql('DROP INDEX IF EXISTS idx_otp_codes_user_valid ON otp_codes');
    }
}
