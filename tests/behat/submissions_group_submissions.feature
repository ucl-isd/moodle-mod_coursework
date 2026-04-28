@mod @mod_coursework @mod_coursework_submissions_group_submissions
Feature: Students are able to submit one piece of work on behalf of the group

  As a student
  I want to be able to submit a single piece of work on behalf of the other people in my group
  So that they and the tutor can see it and mark it

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity  | coursework |
      | course    | C1         |
      | name      | Coursework |
      | usegroups | 1          |
      | maxfiles  | 2          |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | student   | student1 | student1@example.com |
      | student2 | student   | student2 | student12example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student2 | C1     | student |
    And the following "groups" exist:
      | course | idnumber | name     |
      | C1     | G1       | My group |
    And the following "group members" exist:
      | group | user     |
      | G1    | student1 |
      | G1    | student2 |

  @javascript @_file_upload
  Scenario: I can submit a file and it appears for the others to see and resubmit
    Given I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I click on "Upload a submission for your group" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I press "Submit"

    When I am on the "Coursework" "coursework activity" page logged in as "student2"
    Then I should see "Test_document.docx"
    Then I should see "student student1" in the ".behat-groupsubmitter" "css_element"

    When I click on "Edit the submission for your group" "link"
    Then I should see "1" elements in "Upload a file" filemanager

    When I upload "mod/coursework/tests/files_for_uploading/Test_document_two.docx" file to "Upload a file" filemanager
    And I press "Submit"
    And I am on the "Coursework" "coursework activity" page
    Then I should see "Test_document.docx"
    And I should see "Test_document_two.docx"
    And I should see "student student2" in the ".behat-groupsubmitter" "css_element"
