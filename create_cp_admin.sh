#!/bin/bash
# Generate bcrypt hash and create admin user for CloudPanel

HASH=$(php -r 'echo password_hash("admin123", PASSWORD_BCRYPT);')
echo "Hash: $HASH"

sqlite3 /home/clp/htdocs/app/data/db.sq3 "INSERT INTO user (created_at, updated_at, user_name, first_name, last_name, email, password, role, mfa, mfa_secret, status, timezone_id)
VALUES (datetime('now'), datetime('now'), 'admin', 'Sigit', 'Suseno', 'ti.sigitsuseno@gmail.com', '$HASH', 'admin', 0, '', 1, NULL);"

echo "Done. Checking:"
sqlite3 /home/clp/htdocs/app/data/db.sq3 "SELECT user_name, email, role FROM user;"
