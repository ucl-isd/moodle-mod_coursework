Feature: Adding feedback files

    As a teacher
    I want to be able to add feedback files
    So that I can provide users with rich, detailed feedback

    Background:
        Given there is a course
        And there is a coursework
        And the coursework "numberofmarkers" setting is "1" in the database
        And there is a student
        And the student has a submission
        And the submission is finalised

    @javascript
    Scenario: I can upload any file type, regardless of the coursework file types
        Given the coursework "filetypes" setting is "pdf" in the database
        And I am logged in as a teacher
        When I visit the coursework page
        And I click the new single final feedback button for the student
        And I upload "mod/coursework/tests/files_for_uploading/Test_image.png" file to "Upload a file" filemanager
        Then I should see "1" elements in "Upload a file" filemanager

    @javascript
    Scenario: Students see all the feedback files
        Given I am logged in as a manager
        When I visit the coursework page
        And I click the new single final feedback button for the student
        And I upload "mod/coursework/tests/files_for_uploading/Test_image.png" file to "Upload a file" filemanager
        When I upload "mod/coursework/tests/files_for_uploading/Test_document_two.docx" file to "Upload a file" filemanager
        And I press "Save changes"
        And I publish the grades
        And I log out
        And I log in as a student
        And I visit the coursework page
        Then I should see two feedback files on the page


