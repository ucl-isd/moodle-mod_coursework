@mod @mod_coursework @file_renaming_candidate
Feature: Candidate number based file renaming for submission files

  As a teacher
  I want submitted files to be renamed with candidate numbers when using candidate providers
  So that I can ensure blind marking with standardized candidate number anonymization

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "allowearlyfinalisation" setting is "1" in the database
    And there is a editingteacher
    And there is a student
    And the candidate number for the student is "TEST001"

  @javascript @_file_upload
  Scenario: Files are renamed with candidate number when it is enabled
    Given blind marking is enabled
    And the coursework "usecandidate" setting is "1" in the database
    And I am logged in as a student
    When I visit the coursework page
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I save and finalise the submission
    Then the uploaded file should be renamed to "TEST001_1.docx"

  @javascript @_file_upload
  Scenario: Multiple files with candidate numbers get sequential numbering
    Given blind marking is enabled
    And the coursework "usecandidate" setting is "1" in the database
    And the coursework "maxfiles" setting is "2" in the database
    And I am logged in as a student
    When I visit the coursework page
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I upload "mod/coursework/tests/files_for_uploading/Test_document_two.docx" file to "Upload a file" filemanager
    And I save and finalise the submission
    Then the uploaded files should be renamed to:
      | filename        |
      | TEST001_1.docx  |
      | TEST001_2.docx  |

  @javascript @_file_upload
  Scenario: Files use username hash when candidate number is disabled
    Given blind marking is enabled
    And the coursework "usecandidate" setting is "0" in the database
    And I am logged in as a student
    When I visit the coursework page
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I save and finalise the submission
    Then the uploaded file should be renamed with pattern "X[a-f0-9]{8}_1.docx"

  @javascript @_file_upload
  Scenario: Use candidate numbers for file naming setting cannot be changed when submissions with files exist
    Given blind marking is enabled
    And the coursework "usecandidate" setting is "1" in the database
    And I am logged in as a student
    When I visit the coursework page
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I save and finalise the submission
    And I log out
    And I am logged in as a editingteacher
    When I visit the coursework page
    And I click on "Settings" "link"
    And I expand all fieldsets
    And I should see "Candidate number file naming is enabled."
    Then I should see "This setting cannot be changed because students have already submitted files."
