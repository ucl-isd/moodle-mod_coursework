@mod @mod_coursework
Feature: File upload limits

    As a course leader
    I want to be able to limit the number of files that a student can upload
    So that they must submit a specific number

    Background:
        Given there is a course
        And there is a coursework
        And I am logged in as a student

    @javascript
    Scenario: I am prevented from uploading more files than specified
        Given the coursework "maxfiles" setting is "2" in the database

        When I visit the coursework page
        And I click on the new submission button
        And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
        And I upload "mod/coursework/tests/files_for_uploading/Test_document_two.docx" file to "Upload a file" filemanager
        Then the file upload button should not be visible

