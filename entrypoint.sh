#!/bin/sh
set -e

# Generar /etc/msmtprc en tiempo de arranque con las credenciales del entorno
cat > /etc/msmtprc <<EOF
account default
host smtp.gmail.com
port 587
tls on
tls_starttls on
tls_trust_file /etc/ssl/certs/ca-certificates.crt
auth on
user ${MAIL_USER}
password ${MAIL_PASS}
from ${MAIL_USER}
auto_from off
logfile /tmp/msmtp.log
EOF

chmod 644 /etc/msmtprc
touch /tmp/msmtp.log && chmod 666 /tmp/msmtp.log

exec apache2-foreground
