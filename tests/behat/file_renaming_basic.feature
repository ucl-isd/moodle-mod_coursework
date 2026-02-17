@mod @mod_coursework @mod_coursework_file_renaming_basic
Feature: Basic file renaming for submission files

  As a teacher
  I want submitted files to be renamed with anonymous identifiers
  So that I can ensure blind marking integrity and student anonymity

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "allowearlyfinalisation" setting is "1" in the database
    And there is a teacher
    And there is a student

  @javascript @_file_upload
  Scenario: Files are renamed with username hash when renamefiles is enabled
    Given the coursework "renamefiles" setting is "1" in the database
    And I am logged in as a student
    When I visit the coursework page
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I click on "Submit and finalise" "button"
    Then the uploaded file should be renamed with pattern "X[a-f0-9]{8}_1.docx"

  @javascript @_file_upload
  Scenario: Files keep original names when renamefiles is disabled
    Given the coursework "renamefiles" setting is "0" in the database
    And I am logged in as a student
    When I visit the coursework page
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I click on "Submit and finalise" "button"
    # Uploaded file should keep original name "Test_document.docx"
    Then I should see "Test_document.docx"

  @javascript @_file_upload
  Scenario: Multiple files from same student get sequential numbers
    Given the coursework "renamefiles" setting is "1" in the database
    And the coursework "maxfiles" setting is "2" in the database
    And I am logged in as a student
    When I visit the coursework page
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I upload "mod/coursework/tests/files_for_uploading/Test_document_two.docx" file to "Upload a file" filemanager
    And I click on "Submit and finalise" "button"
    Then the uploaded files should be renamed with sequential patterns:
      | pattern                    |
      | X[a-f0-9]{8}_1.docx        |
      | X[a-f0-9]{8}_2.docx        |
