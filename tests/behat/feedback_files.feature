@mod @mod_coursework @_file_upload @mod_coursework_feedback_files
Feature: Adding feedback files

    As a teacher
    I want to be able to add feedback files
    So that I can provide users with rich, detailed feedback

  Background:
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
      | numberofmarkers   | 1          |
    And there is a student
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |

  @javascript
  Scenario: I can upload any file type, regardless of the coursework file types
    Given the coursework "filetypes" setting is "pdf" in the database
    And I am logged in as a teacher
    When I am on the "Coursework" "coursework activity" page
    And I click on the add feedback button
    And I upload "mod/coursework/tests/files_for_uploading/Test_image.png" file to "Upload a file" filemanager
    Then I should see "1" elements in "Upload a file" filemanager

  @javascript
  Scenario: Students see all the feedback files
    Given I am logged in as a manager
    When I am on the "Coursework" "coursework activity" page
    And I click on the add feedback button
    And I set the field "Mark" to "70"
    And I upload "mod/coursework/tests/files_for_uploading/Test_image.png" file to "Upload a file" filemanager
    When I upload "mod/coursework/tests/files_for_uploading/Test_document_two.docx" file to "Upload a file" filemanager
    And I set the field "Mark" to "52"
    And I press "Save and finalise"
    And I should see "Changes saved"
    And I am on the "Coursework" "coursework activity" page
    And I publish the grades
    And I log out

    And I log in as a student
    And I am on the "Coursework" "coursework activity" page
    Then I should see two feedback files on the page
