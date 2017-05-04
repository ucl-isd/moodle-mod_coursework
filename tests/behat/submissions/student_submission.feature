@mod @mod_coursework
Feature: Students can submit files

    In order to submit work to my tutor for grading
    As a student who has completed some work
    I want to be able to upload it as a file to the coursework instance

    Background:
        Given there is a course
        And there is a coursework
        And I am logged in as a student

    @javascript
    Scenario: I upload a file and see it on the coursework page as read only
        When I visit the coursework page
        And I click on the new submission button
        And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
        And I save the submission
        Then I should be on the coursework page
        And I should see the file on the page
        And I should see the edit submission button

    @javascript
    Scenario: I upload a file and save it and I see it when I come back
        When I visit the coursework page
        And I click on the new submission button
        And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
        And I save the submission
        Then I should be on the coursework page
        When I visit the course page
        And I visit the coursework page
        And I click on the edit submission button
        Then I should see "1" elements in "Upload a file" filemanager






