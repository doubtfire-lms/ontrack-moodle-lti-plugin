# OnTrack Course Data LTI Service for Moodle

This is a read-only Moodle LTI service subplugin. Install it at:

```text
mod/lti/service/ontrack
```

Its Moodle component identifier is `ltiservice_ontrack`.

## What it exposes

The plugin provides one authenticated endpoint and one OAuth scope:

```text
GET ontrack_course_data_url
https://ontrack.edu.au/lti/scope/course-data.readonly
```

With no query parameters, the endpoint returns the course's users, enrolments, roles, groups, assignments, due dates and assignment extensions. Use `include` to request only the required sections:

```text
?include=users
?include=users,groups
?include=assignments
?include=users,assignments&assignment_id=123
```

The allowed sections are `users`, `groups` and `assignments`. Course context is always returned. Unrequested sections are omitted. `assignment_id` filters the assignments section to one assignment and is only valid when assignments are included.

Group membership has one canonical representation: `groups[].member_user_ids` refers to `users[].id`. User records do not duplicate this relationship with group IDs.

The signed LTI launch advertises:

```json
{
  "ontrack_course_data_url": "https://moodle.example/mod/lti/services.php/.../ontrack-course-data",
  "ontrack_course_data_scope": "https://ontrack.edu.au/lti/scope/course-data.readonly"
}
```

`doubtfire-lti` requests a short-lived access token from Moodle before each call. The plugin verifies the token, scope, registered tool and course before returning data. It stores nothing and never changes Moodle data.

## Install

Install the release ZIP through **Site administration → Plugins → Install plugins**, then enable **OnTrack course data** in the OnTrack external tool configuration. Perform one fresh LTI launch so `doubtfire-lti` can store the endpoint.

## Maintainer checks

GitHub Actions runs PHP lint, the Moodle code and PHPDoc checkers, plugin validation and PHPUnit against Moodle 4.2, 4.5 and 5.2 on every push to `main` and every pull request. Releases only build after these pass.

To check code style locally (both are installed in the dev container):

```bash
phpcs --standard=moodle .
phpcbf --standard=moodle .
```

PHPUnit runs from a Moodle root with the plugin installed at `mod/lti/service/ontrack`, after `php admin/tool/phpunit/cli/init.php`:

```bash
vendor/bin/phpunit --testsuite ltiservice_ontrack_testsuite
```
