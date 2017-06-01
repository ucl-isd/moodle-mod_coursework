@mod @mod_coursework
Feature: Students are able to submit one piece of work on behalf of the group

    As a student
    I want to be able to submit a single piece of work on behalf of the other people in my group
    So that they and the tutor can see it and mark it

    Background:
        Given there is a course
        And there is a coursework
        And the coursework "use_groups" setting is "1" in the database
        And I am logged in as a student
        And the student is a member of a group
        And there is another student
        And the other student is a member of the group

    @javascript
    Scenario: I can submit a file and it appears for the others to see
        When I visit the coursework page
        And I click on the new submission button
        And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
        And I press "Submit"
        And I log out
        And I log in as the other student
        And I visit the coursework page
        Then I should see the file on the page

    @javascript
    Scenario: I can resubmit the work when someone else has submitted it
        Given the coursework "maxfiles" setting is "2" in the database
        And I visit the coursework page
        And I click on the new submission button
        And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
        And I press "Submit"
        And I log out
        And I log in as the other student
        And I visit the coursework page
        Then I should see that the submission was made by the student

        When I click on the edit submission button
        Then I should see "1" elements in "Upload a file" filemanager

        When I upload "mod/coursework/tests/files_for_uploading/Test_document_two.docx" file to "Upload a file" filemanager

        And I press "Submit"
        Then I should be on the coursework page
        And I should see both the submission files on the page
        And I should see that the submission was made by the other student
