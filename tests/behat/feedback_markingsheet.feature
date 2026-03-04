@mod @mod_coursework @mod_coursework_feedback_zip_upload @javascript @_file_upload

Feature: Upload marking sheet

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity                   | coursework |
      | course                     | C1         |
      | name                       | Coursework |
      | allocationenabled          | 1          |
      | assessorallocationstrategy | none       |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
      | student1 | student   | student1 | student1@example.com |
      | student2 | student   | student2 | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | manager1 | C1     | manager |
      | student1 | C1     | student |
      | student2 | C1     | student |
      | teacher1 | C1     | teacher |
      | teacher2 | C1     | teacher |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
      | student2    | Coursework | 1               |
    And the following "mod_coursework > allocations" exist:
      | allocatable | coursework | assessor | stageidentifier |
      | student1    | Coursework | teacher1 | assessor_1      |
      | student2    | Coursework | teacher1 | assessor_1      |
      | student1    | Coursework | teacher2 | assessor_2      |

  Scenario: Teachers at both stages should be able to download, edit and upload a marking sheet
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I click on "Download" "button"
    And I click on "Marking spreadsheet" "link"
    Then I should see "\"Sub ID\",\"Submission file id\",Surname/Name,Username,\"ID number\",\"Email address\",\"Submission time\",\"Marker 1\",\"Marker 1 mark\",\"Marker 1 feedback\",\"Marker 2\",\"Marker 2 mark\",\"Marker 2 feedback\",\"Agreed mark\",\"Agreed mark feedback\""
    And I should see "\"student1 student\",student1,,student1@example.com,\"On time\",\"teacher1 teacher\",,,\"teacher2 teacher\",,,,"
    And I should see "\"student2 student\",student2,,student2@example.com,\"On time\",\"teacher1 teacher\",,,,,,,"

    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on "Download" "button"
    And I click on "Marking spreadsheet" "link"
    Then I should see "\"Sub ID\",\"Submission file id\",Surname/Name,Username,\"ID number\",\"Email address\",\"Submission time\",Mark,\"Feedback comment\""
    And I should see "\"student1 student\",student1,,student1@example.com,\"On time\""
    And I should see "\"student2 student\",student2,,student2@example.com,\"On time\""

    Given I am on the "Coursework" "coursework activity" page
    And I click on "Upload" "button"
    And I click on "Marking spreadsheet" "link" in the ".dropdown-menu.show" "css_element"
    And I upload "mod/coursework/tests/files_for_uploading/marking_sheet_simpleassessor_two_learners.csv" file to "Marking sheet file" filemanager
    And I click on "Upload marking sheet" "button"
    And I follow "Continue to coursework"
    Then I should see "15" in the "student student1" "table_row"
    And I should see "50" in the "student student2" "table_row"

    Given the following "permission overrides" exist:
      | capability                             | permission | role    | contextlevel | reference |
      | mod/coursework:addallocatedagreedgrade | Allow      | teacher | Course       | C1        |

    Given I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    And I click on "Download" "button"
    And I click on "Marking spreadsheet" "link"
    Then I should see "\"Sub ID\",\"Submission file id\",Surname/Name,Username,\"ID number\",\"Email address\",\"Submission time\",Mark,\"Feedback comment\",\"Other marker mark (1)\",\"Other marker feedback (1)\",\"Agreed mark\",\"Agreed mark feedback\""
    And I should see "\"student1 student\",student1,,student1@example.com,\"On time\",,,\"Hidden until all initial marks are complete\",,,"
    And I should not see "student2"

    Given I am on the "Coursework" "coursework activity" page
    And I click on "Upload" "button"
    And I click on "Marking spreadsheet" "link" in the ".dropdown-menu.show" "css_element"
    And I upload "mod/coursework/tests/files_for_uploading/marking_sheet_secondassessor_canagree_two_learners.csv" file to "Marking sheet file" filemanager
    And I click on "Upload marking sheet" "button"
    And I follow "Continue to coursework"
    Then I should see "15" in the "student student1" "table_row"
    And I should see "75" in the "student student1" "table_row"

    Given I am on the "Coursework" "coursework activity" page
    And I click on "Download" "button"
    And I click on "Marking spreadsheet" "link"
    Then I should see "\"Sub ID\",\"Submission file id\",Surname/Name,Username,\"ID number\",\"Email address\",\"Submission time\",Mark,\"Feedback comment\",\"Other marker mark (1)\",\"Other marker feedback (1)\",\"Agreed mark\",\"Agreed mark feedback\""
    And I should see "\"student1 student\",student1,,student1@example.com,\"On time\",75,,15,,,"
    And I should not see "student2"
