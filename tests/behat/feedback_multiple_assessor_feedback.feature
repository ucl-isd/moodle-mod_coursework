@mod @mod_coursework @mod_coursework_feedback_multiple_assessors
Feature: Multiple assessors simple grading form

    As a teacher
    I want there to be a simple grading form
    So that I can give students a grade and a feedback comment without any frustrating extra work

  Background:
    Given there is a course
    And the following "permission overrides" exist:
            | capability                      | permission | role    | contextlevel | reference |
            | mod/coursework:editinitialgrade | Allow      | teacher | Course       | C1        |
    And there is a coursework
    And the coursework "numberofmarkers" setting is "2" in the database
    And the coursework "allocationenabled" setting is "0" in the database
    And there is a student
    And the student has a submission

  Scenario: Grade and comments can be saved
    Given I am logged in as a teacher
    And the submission is finalised
    And I visit the coursework page
    And I click on "Add feedback" "link"
    And I set the field "Grade" to "52"
    And I set the field "Comment" to "Some new comment 3"
    And I click on "Save and finalise" "button"
    And I wait until "OK" "button" exists
    And I visit the edit feedback page
    And the field "Grade" matches value "52"
    And the field "Comment" matches value "Some new comment 3"

  @javascript @_file_upload
  Scenario: Grade files can be saved
    Given I am logged in as a teacher
    And the submission is finalised
    And I visit the coursework page
    And I click on "Add feedback" "link"
    When I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I press "Save and finalise"
    And I visit the coursework page
    And I click the edit feedback button
    Then I should see "1" elements in "Upload a file" filemanager

  @javascript
  Scenario: Grade comments can be edited
    Given I am logged in as a teacher
    And the submission is finalised
    And I have an assessor feedback at grade 67
    And I visit the coursework page
    And I click the edit feedback button
    And the grade comment textarea field matches "New comment here"

  Scenario: Grades can not be edited by other teachers
    Given there is a teacher
    And there is another teacher
    And there is feedback for the submission from the teacher
    And I am logged in as the other teacher
    And the submission is finalised
    When I visit the coursework page
    Then show me the page
    Then I should not see the edit feedback button for the teacher's feedback

  @javascript @_file_upload
  Scenario: Grade files can be edited and more are added
    Given I am logged in as a teacher
    And the submission is finalised
    And I visit the coursework page
    And I click on "Add feedback" "link"
    When I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I press "Save and finalise"
    And I visit the coursework page
    And I click the edit feedback button
    And I upload "mod/coursework/tests/files_for_uploading/Test_document_two.docx" file to "Upload a file" filemanager
    Then I should see "2" elements in "Upload a file" filemanager
    When I press "Save and finalise"
    And I visit the coursework page
    And I click the edit feedback button
    Then I should see "2" elements in "Upload a file" filemanager

  Scenario: I should not see the feedback icon when the submission has not been finalised
    Given I am logged in as a teacher
    And I visit the coursework page
    Then I should not see a link to add feedback

  @javascript
  Scenario: managers can grade the initial stages
    Given I am logged in as a manager
    And the submission is finalised
    And I visit the coursework page
    And I click on "Add feedback" "link"
    When I grade the submission as 56 using the simple form with comment "A test comment 9"
    And I visit the coursework page
    Then I should see the grade on the page
