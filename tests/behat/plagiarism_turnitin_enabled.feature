@mod @mod_coursework @mod_coursework_plagiarism_turnitin_links @_file_upload  @javascript
Feature: Check that Turnitin shows up in the UI when fully configured.

  Background:
    Given Turnitin has been configured for behat
    And the following config values are set as admin:
      | config                             | value | plugin              |
      | enableplagiarism                   | 1     |                     |
      | enabled                            | 1     | plagiarism_turnitin |
      | plagiarism_turnitin_mod_coursework | 1     | plagiarism_turnitin |
    And the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity                          | coursework |
      | course                            | C1         |
      | name                              | Coursework |
      | numberofmarkers                   | 1          |
      | use_turnitin                      | 1          |
      | plagiarismflagenabled             | 1          |
      | plagiarism_compare_student_papers | 1          |
      | plagiarism_report_gen             | 1          |
      | plagiarism_show_student_report    | 1          |
      | plagiarism_compare_internet       | 0          |
      | plagiarism_compare_journals       | 0          |
      | plagiarism_compare_institution    | 0          |
      | plagiarism_exclude_biblio         | 0          |
      | plagiarism_exclude_quoted         | 0          |
      | plagiarism_exclude_matches        | 0          |
      | allowearlyfinalisation            | 1          |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | student1 | student   | student1 | student1@example.com |
      | manager1 | manager   | manager1 | manager1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | student1 | C1     | student |
      | manager1 | C1     | manager |
    And I log in as "admin"
    And I navigate to "Plugins > Plagiarism > Turnitin plagiarism plugin" in site administration
    And I configure Turnitin URL
    And I configure Turnitin credentials
    And I set the following fields to these values:
      | Enable Diagnostic Mode | Standard |
    And I press "Save changes"
    And I mark this test as slow setting a timeout factor of 10

  Scenario: Turnitin information and links show for students and teachers.
    Given I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I click on "Upload your submission" "link"
    And I accept the Turnitin EULA if necessary
    And I wait until the page is ready
    And I wait until "Turnitin may restrict allowed file types to those it can process." "text" exists
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    When I press "Submit and finalise"
    Then I should see "Turnitin status: Queued" in the ".turnitin_status" "css_element"

    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    # Ensure the table is scrolled into view, since JS loading is not triggered for rows out of view.
    When I hover "table.mod-coursework-submissions-table" "css_element"
    And I wait until the page is ready
    Then I should see "Turnitin status: Queued" in the "student1" "table_row"

    Given I trigger cron

    When I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "Turnitin ID:" in the ".tii_links_container" "css_element"

    When I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I hover "table.mod-coursework-submissions-table" "css_element"
    And I wait until the page is ready
    Then I should see "Turnitin ID:" in the "student1" "table_row"
    And "[title='GradeMark']" "css_element" should exist in the "student1" "table_row"
