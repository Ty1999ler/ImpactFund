#!/bin/sh
# CI fixture — mounted over /usr/sbin/sendmail in the smoke-test container so
# PHP mail() "succeeds" without a real MTA. mail()'s return value is just the
# sendmail exit status, so exiting 0 makes contact.php take its 200 path.
# The raw message (headers + body arrive on stdin) is captured to
# /tmp/mail.out for the workflow to docker-exec back out and assert on.
# sendmail's -t / -i / -f arguments are ignored on purpose.
cat > /tmp/mail.out
exit 0
