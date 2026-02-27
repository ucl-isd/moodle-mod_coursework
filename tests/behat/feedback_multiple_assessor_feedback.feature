@mod @mod_coursework @mod_coursework_feedback_multiple_assessors
Feature: Multiple assessors simple grading form

    As a teacher
    I want there to be a simple grading form
    So that I can give students a grade and a feedback comment without any frustrating extra work

  Background:
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And there is a student
    And there is a teacher
    And there is another teacher
    And the following "permission overrides" exist:
            | capability                      | permission | role    | contextlevel | reference |
            | mod/coursework:editinitialgrade | Allow      | teacher | Course       | C1        |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
      | numberofmarkers            | 2          |
    And the coursework "allocationenabled" setting is "0" in the database
    And the student has a submission

  Scenario: Grade and comments can be saved
    Given I am logged in as the teacher
    And the submission is finalised
    And I am on the "Coursework" "coursework activity" page
    And I click on "Add mark" "link"
    And I set the field "Mark" to "52"
    And I set the field "Comment" to "Some new comment 3"
    And I click on "Save and finalise" "button"
    And I should see "Changes saved"
    And I visit the edit feedback page
    And the field "Mark" matches value "52"
    And the field "Comment" matches value "Some new comment 3"

  @javascript @_file_upload
  Scenario: Grade files can be saved
    Given I am logged in as a teacher
    And the submission is finalised
    And I am on the "Coursework" "coursework activity" page
    And I click on "Add mark" "link"
    And I set the field "Mark" to "70"
    When I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I set the field "Mark" to "52"
    And I press "Save and finalise"
    And I should see "Changes saved"
    And I am on the "Coursework" "coursework activity" page
    And I click the edit feedback button
    Then I should see "1" elements in "Upload a file" filemanager

  @javascript
  Scenario: Grade comments can be edited
    Given I am logged in as a teacher
    And the submission is finalised
    And I have an assessor feedback at grade 67
    And I am on the "Coursework" "coursework activity" page
    And I click the edit feedback button
    And the following fields match these values:
      | Comment | New comment here |

  Scenario: Grades can not be edited by other teachers
    Given there is feedback for the submission from the teacher
    And I am logged in as the other teacher
    And the submission is finalised
    When I am on the "Coursework" "coursework activity" page
    Then show me the page
    Then I should not see the edit feedback button for the teacher's feedback

  @javascript @_file_upload
  Scenario: Grade files can be edited and more are added
    Given I am logged in as a teacher
    And the submission is finalised
    And I am on the "Coursework" "coursework activity" page
    And I click on "Add mark" "link"
    And I set the field "Mark" to "70"
    When I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I set the field "Mark" to "52"
    And I press "Save and finalise"
    And I should see "Changes saved"
    And I am on the "Coursework" "coursework activity" page
    And I click the edit feedback button
    And I upload "mod/coursework/tests/files_for_uploading/Test_document_two.docx" file to "Upload a file" filemanager
    Then I should see "2" elements in "Upload a file" filemanager
    When I press "Save and finalise"
    And I am on the "Coursework" "coursework activity" page
    And I click the edit feedback button
    Then I should see "2" elements in "Upload a file" filemanager

  Scenario: I should not see the feedback icon when the submission has not been finalised
    Given I am logged in as a teacher
    And I am on the "Coursework" "coursework activity" page
    Then I should not see a link to add feedback

  @javascript
  Scenario: managers can grade the initial stages
    Given I am logged in as a manager
    And the submission is finalised
    And I am on the "Coursework" "coursework activity" page
    And I click on "Add mark" "link" in the "Submissions table" "table"
    When I grade the submission as 56 using the simple form with comment "A test comment 9"
    And I am on the "Coursework" "coursework activity" page
    Then I should see the grade on the page

  Scenario: Teachers do not see the agree marking button unless they have the specific permission awarded
    Given there is a teacher
    And there is another teacher
    And the submission is finalised
    And there is finalised feedback for the submission from the teacher
    And I am logged in as the other teacher
    And I am on the "Coursework" "coursework activity" page
    And I follow "Add mark"
    And I set the field "Mark" to "59"
    And I press "Save and finalise"
    And I should see "Changes saved"
    And I am on the "Coursework" "coursework activity" page
    # Cannot see agree marking until specific capability awarded.
    Then I should not see "Agree marking"
    And the following "permission overrides" exist:
      | capability                      | permission | role    | contextlevel | reference |
      | mod/coursework:addagreedgrade   | Allow      | teacher | Course       | C1        |
    And I am on the "Coursework" "coursework activity" page
    And I follow "Agree marking"
    And I wait until the page is ready
    And I set the field "Mark" to "71.1"
    And I press "Save and finalise"
    And I am on the "Coursework" "coursework activity" page
    And I should see "71.1"

  Scenario: As a teacher I should not be able to see who else is grading a submission until all grades are in added to it if viewinitialgradeenabled is no
    Given the coursework "viewinitialgradeenabled" setting is "0" in the database
    And I am logged in as the teacher
    And the submission is finalised
    And I am on the "Coursework" "coursework activity" page
    And I should not see "teacher3" in the "student1" "table_row"
    And I click on "Add mark" "link"
    And I set the field "Mark" to "52"
    And I set the field "Comment" to "Some new comment 3"
    And I click on "Save and finalise" "button"
    And I am on the "Coursework" "coursework activity" page
    And I should see "teacher2" in the "student1" "table_row"
    And I should not see "teacher3" in the "student1" "table_row"
    And I log out
    # Manager should also see the marker's name.
    And I log in as a manager
    And I am on the "Coursework" "coursework activity" page
    And I should see "teacher2" in the "student1" "table_row"
    And I log out
    # Teacher 2 should not see the teacher1's name until both grades are in.
    And I log in as the other teacher
    And I am on the "Coursework" "coursework activity" page
    And I should not see "teacher2" in the "student1" "table_row"
    And I click on "Add mark" "link"
    And I set the field "Mark" to "55"
    And I set the field "Comment" to "Some new comment 8"
    And I click on "Save and finalise" "button"
    And I am on the "Coursework" "coursework activity" page
    # Now that both grades have been added, should also see teacher 1 identity
    And I should see "teacher2" in the "student1" "table_row"
    And I should see "teacher3" in the "student1" "table_row"

  Scenario: As a teacher I should be able to see who else has graded a submission if viewinitialgradeenabled is yes
    Given the coursework "viewinitialgradeenabled" setting is "1" in the database
    And I log in as the teacher
    And the submission is finalised
    And I am on the "Coursework" "coursework activity" page
    And I click on "Add mark" "link"
    And I set the field "Mark" to "52"
    And I set the field "Comment" to "Some new comment 3"
    And I click on "Save and finalise" "button"
    And I am on the "Coursework" "coursework activity" page
    And I should see "teacher2" in the "student1" "table_row"
    And I log out
    # Manager should see the marker's name.
    And I log in as a manager
    And I am on the "Coursework" "coursework activity" page
    And I should see "teacher2" in the "student1" "table_row"
    And I log out
    # Teacher 2 should also see teacher1's name even if both grades are not yet in.
    And I log in as the other teacher
    And I am on the "Coursework" "coursework activity" page
    And I should see "teacher2" in the "student1" "table_row"
    And I click on "Add mark" "link"
    And I set the field "Mark" to "55"
    And I set the field "Comment" to "Some new comment 8"
    And I click on "Save and finalise" "button"
    And I am on the "Coursework" "coursework activity" page
    And I should see "teacher2" in the "student1" "table_row"
    And I should see "teacher3" in the "student1" "table_row"
