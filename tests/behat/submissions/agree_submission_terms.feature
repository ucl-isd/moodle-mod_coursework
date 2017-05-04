@mod @mod_coursework
Feature: Students must agree to terms before submitting anything

    As a manger
    I want to be able to force students to agree to terms and conditions
    So that we are legally protected in case of disputes over plagiarism and the students can't cheat

    Background:
        Given there is a course
        And there is a coursework
        And I am logged in as a student

    Scenario: I see the terms when the site has the option enabled
        Given the sitewide "coursework_agree_terms" setting is "1"
        And the sitewide "coursework_agree_terms_text" setting is "Some text"
        When I visit the coursework page
        And I click on the new submission button
        Then I should be on the new submission page
        And I should see "Some text"

    Scenario: I do not see the terms when the site has the option disabled
        Given the sitewide "coursework_agree_terms" setting is "0"
        And the sitewide "coursework_agree_terms_text" setting is "Some text"
        When I visit the coursework page
        And I click on the new submission button
        Then I should be on the new submission page
        And I should not see "Some text"

    @javascript
    Scenario: The submission is saved when the agree terms checkbox is checked during create
        Given the sitewide "coursework_agree_terms" setting is "1"
        And the sitewide "coursework_agree_terms_text" setting is "Some text"
        When I visit the coursework page
        And I click on the new submission button
        And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
        And I set the field "termsagreed" to "1"
        And I press "Submit"
        Then I should be on the coursework page

    @javascript
    Scenario: The submission is not saved when the agree terms checkbox is not checked during create
        Given the sitewide "coursework_agree_terms" setting is "1"
        And the sitewide "coursework_agree_terms_text" setting is "Some text"
        When I visit the coursework page
        And I click on the new submission button
        And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
        And I press "Submit"
        Then I should be on the create submission page

    @javascript
    Scenario: The submission is saved when the agree terms checkbox is checked during update
        Given the sitewide "coursework_agree_terms" setting is "1"
        And the sitewide "coursework_agree_terms_text" setting is "Some text"
        And the student has a submission
        When I visit the coursework page
        And I click on the edit submission button
        And I set the field "termsagreed" to "1"
        And I press "Submit"
        Then I should be on the coursework page

    @javascript
    Scenario: The submission is not saved when the agree terms checkbox is not checked during update
        Given the sitewide "coursework_agree_terms" setting is "1"
        And the sitewide "coursework_agree_terms_text" setting is "Some text"
        And the student has a submission
        When I visit the coursework page
        And I click on the edit submission button
        And I press "Submit"
        Then I should be on the update submission page

    @javascript
    Scenario: The file should not be saved if the agree terms are skipped on create
        Given the sitewide "coursework_agree_terms" setting is "1"
        And the sitewide "coursework_agree_terms_text" setting is "Some text"
        When I visit the coursework page
        And I click on the new submission button
        And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
        And I press "Submit"
        Then I should be on the create submission page
        When I visit the coursework page
        Then I should not see the file on the page

    @javascript
    Scenario: The file should not be saved if the agree terms are skipped on update
        Given the sitewide "coursework_agree_terms" setting is "1"
        And the sitewide "coursework_agree_terms_text" setting is "Some text"
        And the coursework "maxfiles" setting is "2" in the database
        And the student has a submission
        When I visit the coursework page
        And I click on the edit submission button
        And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
        And I press "Submit"
        Then I should be on the update submission page
        When I visit the coursework page
        Then I should see 1 file on the page

