@mod @mod_coursework @_file_upload
Feature: Students must agree to terms before submitting anything

  As a manger
  I want to be able to force students to agree to terms and conditions
  So that we are legally protected in case of disputes over plagiarism and the students can't cheat

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following config values are set as admin:
      | config                      | value     |
      | coursework_agree_terms_text | Some text |
      | coursework_agree_terms      | 1         |

  Scenario: I do not see the terms when the site has the option disabled
    Given the following config values are set as admin:
      | config                 | value |
      | coursework_agree_terms | 0     |
    When I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I click on "Upload your submission" "link"
    Then I should see "Upload your submission"
    And I should not see "Some text"

  @javascript
  Scenario: The submission is saved when the agree terms checkbox is checked during create
    Given I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I click on "Upload your submission" "link"
    Then I should see "Upload your submission"
    And I should see "Some text"

    When I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I set the field "termsagreed" to "0"
    And I press "Submit"
    Then I should see "You must supply a value here."
    And I should see "Upload your submission"

    When I set the field "termsagreed" to "1"
    And I press "Submit"
    Then I should see "Test_document.docx"
    And I should see "Edit your submission"

  @javascript
  Scenario: The submission is saved when the agree terms checkbox is checked during update
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 0               |
    When I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I click on "Edit your submission" "link"
    And I set the field "termsagreed" to "0"
    And I press "Submit"
    Then I should see "You must supply a value here."
    And I should see "Edit your submission"

    When I set the field "termsagreed" to "1"
    And I press "Submit"
    Then I should not see "You must supply a value here."
    And I should see "Edit your submission"
