# OnTrack Course Data LTI Service for Moodle

A read-only Moodle LTI service that lets the registered OnTrack tool fetch course data: users, enrolments, roles, groups, assignments, due dates and extensions. It stores nothing and never changes Moodle data.

- Component: `ltiservice_ontrack`
- Install path: `mod/lti/service/ontrack`
- Requires: Moodle 4.2 or later

## Install

1. Install the release ZIP through **Site administration → Plugins → Install plugins**.
2. In the OnTrack external tool configuration, set **OnTrack course data** to **Allow**.
3. Launch OnTrack from a course once, so it can store the endpoint.

## How it works

1. Each LTI launch from a course that uses the OnTrack tool includes the endpoint and scope:

    ```json
    {
        "ontrack_course_data_url": "https://moodle.example/mod/lti/services.php/.../ontrack-course-data",
        "ontrack_course_data_scope": "https://ontrack.edu.au/lti/scope/course-data.readonly"
    }
    ```

2. OnTrack requests a short-lived access token for that scope from Moodle.
3. The plugin checks the token, scope, tool and course, then returns the data.

If the service is set to **Do not expose**, launches don't include the endpoint and requests are refused.

## Endpoint

```text
GET {ontrack_course_data_url}
```

By default all sections are returned. Use `include` to choose sections:

| Query                                    | Returns                             |
| ---------------------------------------- | ----------------------------------- |
| _(none)_                                 | `users`, `groups` and `assignments` |
| `?include=users,groups`                  | only the listed sections            |
| `?include=assignments&assignment_id=123` | one assignment                      |

- Allowed sections are `users`, `groups` and `assignments`. Course details are always returned.
- `assignment_id` only works when `assignments` is included.
- Group members are listed once, in `groups[].member_user_ids`, which refer to `users[].id`.

## Data shared

Site admins should be aware that the endpoint returns:

- every enrolment in the course, including suspended and expired ones, with each user's email regardless of their email-visibility setting
- roles assigned in the course and in its parent category and system contexts
- members of all groups, including groups whose membership is hidden
- assignment due dates, per-user extensions and user or group overrides

## Development

GitHub Actions runs PHP lint, the Moodle code and PHPDoc checkers, plugin validation and PHPUnit against Moodle 4.2, 4.5 and 5.2 on every push to `main` and every pull request. Releases only build once these pass.

Check and fix code style locally with [moodle-cs](https://github.com/moodlehq/moodle-cs):

```bash
phpcs --standard=moodle .
phpcbf --standard=moodle .
```

Run the tests from a Moodle root with the plugin installed, after `php admin/tool/phpunit/cli/init.php`:

```bash
vendor/bin/phpunit --testsuite ltiservice_ontrack_testsuite
```
