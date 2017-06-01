Feature: Early finalisation of student submissions

    As a teacher
    I want to allow students to finalise their work early
    So that there is a way to know when something is ready to mark before the deadline is due and I
    can plan my grading work more effectively

    Background:
        Given there is a course
        And there is a coursework
        And the coursework "allowearlyfinalisation" setting is "1" in the database
        And I am logged in as a student

    @javascript
    Scenario: I upload a file and finalise it immediately
        When I visit the coursework page
        And I click on the new submission button
        And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
        And I save and finalise the submission
        And I visit the coursework page
        Then I should see the file on the page
        And I should not see the edit submission button
        And I should not see the finalise submission button

    @broken @javascript
    Scenario: I upload a file and save it
        When I visit the coursework page
        And I click on the new submission button
        And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
        And I save the submission
        And I visit the coursework page
        And I click on the finalise submission button
        And I agree to the confirm message
        Then I should be on the coursework page
        And I should not see the finalise submission button

    @javascript
    Scenario: I should not see the early finalisation button on the student page when the option is disabled
        Given the coursework "allowearlyfinalisation" setting is "0" in the database
        When I visit the coursework page
        And I click on the new submission button
        And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
        And I save the submission
        And I visit the coursework page
        Then I should not see the finalise button

    Scenario: I should not see the early finalisation button on the student submission form
        Given the coursework "allowearlyfinalisation" setting is "0" in the database
        When I visit the coursework page
        And I click on the new submission button
        Then I should not see the save and finalise button