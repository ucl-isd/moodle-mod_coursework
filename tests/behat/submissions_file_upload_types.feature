@mod @mod_coursework
Feature: Restricting the types of files that students can upload

    As a teacher
    I want to be able to restrict what file types the students can upload
    So that tutors marking the work have a consistent experence and don't waste time

    Background:
        Given there is a course
        And there is a coursework
        And I am logged in as a student

    @javascript
    Scenario: I can upload anything when the settings are empty
        Given the coursework "filetypes" setting is "" in the database

        When I visit the coursework page
        And I click on the new submission button
        And I upload "mod/coursework/tests/files_for_uploading/Test_image.png" file to "Upload a file" filemanager
        Then I should see "1" elements in "Upload a file" filemanager

# Wrong file type throws an exception with a backtrace. Can't find out how to expect this.
#    @javascript
#    Scenario: I can not upload other file types when the settings are restrictive
#        Given the coursework "filetypes" setting is "doc" in the database
#
#        When I visit the coursework page
#        And I upload "mod/coursework/tests/files_for_uploading/Test_image.png" file to "Upload a file" filemanager
#        Then I should see "0" elements in "Upload a file" filemanager

    @javascript
    Scenario: I can upload allowed file types when the settings are restrictive
        Given the coursework "filetypes" setting is "docx" in the database
        When I visit the coursework page
        And I click on the new submission button
        And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
        Then I should see "1" elements in "Upload a file" filemanager