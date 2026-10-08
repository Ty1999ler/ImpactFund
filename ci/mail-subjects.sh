#!/bin/sh
# CI helper — prints the decoded Subject: of every message in a file captured
# by ci/fake-sendmail.sh (/tmp/mail.out or /tmp/mail.all), one per line.
# api/_lib.php send_mail() always writes the subject as a single
# =?UTF-8?B?...?= encoded word.  Usage: sh ci/mail-subjects.sh <file>
tr -d '\r' < "$1" | sed -n 's/^Subject: =?UTF-8?B?\(.*\)?=$/\1/p' | while read -r b; do
  printf '%s' "$b" | base64 -d
  echo
done
