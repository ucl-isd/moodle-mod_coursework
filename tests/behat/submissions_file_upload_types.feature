@mod @mod_coursework @mod_coursework_submissions_file_upload_types
Feature: Restricting the types of files that students can upload

    As a teacher
    I want to be able to restrict what file types the students can upload
    So that tutors marking the work have a consistent experence and don't waste time

  Background:
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
    And I am logged in as a student

  @javascript @_file_upload
  Scenario: I can upload anything when the settings are empty
    Given the coursework "filetypes" setting is "" in the database

    When I am on the "Coursework" "coursework activity" page
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_image.png" file to "Upload a file" filemanager
    Then I should see "1" elements in "Upload a file" filemanager

# Wrong file type throws an exception with a backtrace. Can't find out how to expect this.
#  @javascript @_file_upload
#  Scenario: I can not upload other file types when the settings are restrictive
#    Given the coursework "filetypes" setting is "doc" in the database
#
#    When I am on the "Coursework" "coursework activity" page
#    And I upload "mod/coursework/tests/files_for_uploading/Test_image.png" file to "Upload a file" filemanager
#    Then I should see "0" elements in "Upload a file" filemanager

  @javascript @_file_upload
  Scenario: I can upload allowed file types when the settings are restrictive
    Given the coursework "filetypes" setting is "docx" in the database
    When I am on the "Coursework" "coursework activity" page
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    Then I should see "1" elements in "Upload a file" filemanager
