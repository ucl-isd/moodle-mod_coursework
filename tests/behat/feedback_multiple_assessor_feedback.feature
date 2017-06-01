@mod @mod_coursework
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

    Scenario: Grades can be saved
        Given I am logged in as a teacher
        And the submission is finalised
        And I visit the coursework page
        And I click on the new feedback button for assessor 1
        When I grade the submission using the simple form
        Then I should see the grade on the page

    Scenario: Grade comments can be saved
        Given I am logged in as a teacher
        And the submission is finalised
        And I visit the coursework page
        And I click on the new feedback button for assessor 1
        When I grade the submission using the simple form
        And I visit the edit feedback page
        Then I should see the grade comment in the form on the page

    @javascript
    Scenario: Grade files can be saved
        Given I am logged in as a teacher
        And the submission is finalised
        And I visit the coursework page
        And I click on the new feedback button for assessor 1
        When I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
        And I press "Save changes"
        And I click on the edit feedback icon
        Then I should be on the edit feedback page
        Then I should see "1" elements in "Upload a file" filemanager

    Scenario: Grade comments can be edited
        Given I am logged in as a teacher
        And the submission is finalised
        And I have an assessor feedback
        And I visit the coursework page
        And I click on the edit feedback icon
        Then I should see the grade comment in the form on the page

    Scenario: Grades can not be edited by other teachers
        Given there is a teacher
        And there is another teacher
        And there is feedback for the submission from the teacher
        And I am logged in as the other teacher
        And the submission is finalised
        When I visit the coursework page
        Then show me the page
        Then I should not see the edit feedback button for the teacher's feedback

    @javascript
    Scenario: Grade files can be edited and more are added
        Given I am logged in as a teacher
        And the submission is finalised
        And I visit the coursework page
        And I click on the new feedback button for assessor 1
        And I wait "1" seconds
        When I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
        And I press "Save changes"
        And I click on the edit feedback icon
        And I wait "2" seconds
        And I upload "mod/coursework/tests/files_for_uploading/Test_document_two.docx" file to "Upload a file" filemanager
        Then I should see "2" elements in "Upload a file" filemanager

        When I press "Save changes"
        And I click on the edit feedback icon
        And I wait "1" seconds
        Then I should see "2" elements in "Upload a file" filemanager

    Scenario: I should not see the feedback icon when the submisison has not been finalised
        Given I am logged in as a teacher
        And I visit the coursework page
        Then I should not see a link to add feedback

    Scenario: managers can grade the initial stages
        Given I am logged in as a manager
        And the submission is finalised
        And I visit the coursework page
        And I click on the new feedback button for assessor 1
        When I grade the submission using the simple form
        Then I should see the grade on the page



