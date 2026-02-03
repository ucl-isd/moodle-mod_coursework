@mod @mod_coursework @javascript @mod_coursework_plagiarism_turnitin_links
Feature: Check that Turnitin reports are fetched and displayed post page load from JS (when enabled)
#  These tests can be expected to fail if the plagiarism/turnitin plugin is not also installed in the build.

  Background:
    Given there is a course
    And there is a coursework
    And there is a student
    And there is a teacher
    And the student has a submission
    And the submission is finalised

  Scenario: Submission does *not* have Turnitin report on page load as settings are off
    Given I am logged in as a teacher
    And I visit the coursework page
    # Ensure the table is scrolled into view, since JS loading is not triggered for rows out of view.
    And I scroll to the element "table.mod-coursework-submissions-table"
    And I wait until the page is ready
    And I should not see "[TURNITIN DUMMY LINKS HTML]"

  Scenario: Submission does *not* have Turnitin report on page load as global settings are on but course setting is still off
    Given I am logged in as a teacher
    Given the following config values are set as admin:
      | config                             | value    |
      | enableplagiarism                   | 1        |
    And the following config values are set as admin:
      | config                             | value    | plugin              |
      | enabled                            | 1        | plagiarism_turnitin |
      | plagiarism_turnitin_mod_coursework | 1        | plagiarism_turnitin |
    And I visit the coursework page
    # Ensure the table is scrolled into view, since JS loading is not triggered for rows out of view.
    And I scroll to the element "table.mod-coursework-submissions-table"
    And I wait until the page is ready
    # The course specific setting is still off at this point so I do not see the links.
    And I should not see "[TURNITIN DUMMY LINKS HTML]"

  Scenario: Submission *does* have Turnitin report on page load as settings are on
    Given the following config values are set as admin:
      | config                             | value    |
      | enableplagiarism                   | 1        |
    And the following config values are set as admin:
      | config                             | value    | plugin              |
      | enabled                            | 1        | plagiarism_turnitin |
      | plagiarism_turnitin_mod_coursework | 1        | plagiarism_turnitin |
    And the coursework "plagiarism_turnitin_config" setting is "1" in the database
    And I am logged in as a teacher
    And I visit the coursework page
    # Ensure the table is scrolled into view, since JS loading is not triggered for rows out of view.
    And I scroll to the element "table.mod-coursework-submissions-table"
    And I wait until the page is ready
    # Now the course specific setting is *on* so I do see the links.
    And I should see "[TURNITIN DUMMY LINKS HTML]"
