Feature: Late submissions

    As a teacher
    I want to be able to allow stuents to submit work past the deadline
    So that they can still get some credit even if their grades get capped

    Background:
        Given there is a course
        And there is a coursework
        And I am logged in as a student

    Scenario: not allowed to submit late if the setting does not allow it
        Given the coursework "allowlatesubmissions" setting is "0" in the database
        And the submission deadline has passed
        When I visit the coursework page
        Then I should not see the new submission button

    @javascript
    Scenario: allowed to submit late if the setting allows it
        Given the coursework "allowlatesubmissions" setting is "1" in the database
        And the submission deadline has passed
        When I visit the coursework page
        Then I should see the new submission button
        When I visit the new submission page
        And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
        And I press "Submit"
        Then I should be on the coursework page


