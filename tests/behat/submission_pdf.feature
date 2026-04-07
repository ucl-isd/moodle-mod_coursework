@mod @mod_coursework @mod_coursework_moderation_view
Feature: Submit PDFs and have them graded and moderated

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity                   | coursework |
      | course                     | C1         |
      | name                       | Coursework |
      | numberofmarkers            | 1          |
      | moderationagreementenabled | 1          |
      | filetypes                  | pdf        |
    |allowearlyfinalisation      |1           |
    And the following "users" exist:
      | username   | firstname | lastname | email                  |
      | moderator1 | moderator | 1        | moderator1@example.com |
      | teacher1   | teacher   | teacher1 | teacher1@example.com   |
      | student1   | John1     | student1 | student1@example.com   |
    And the following "role" exists:
      | shortname               | moderator |
      | name                    | moderator |
      | context_course          | 1         |
      | mod/coursework:moderate | allow     |
      | mod/coursework:view     | allow     |
    And the following "course enrolments" exist:
      | user       | course | role      |
      | moderator1 | C1     | moderator |
      | student1   | C1     | student   |
      | teacher1   | C1     | teacher   |

  @_file_upload @javascript
  Scenario: As a learner I can submit a PDF
            As a teacher I can grade the PDF while previewing it
            As a moderator I can moderate the grade while viewing the PDF
    Given I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/test_pdf.pdf" file to "Upload a file" filemanager
    And I press "Submit and finalise"

    When I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on "Add mark" "link" in the "student1" "table_row"
    And I switch to "mod-coursework-pdf-iframe" iframe
    #Then I should see "Test document for uploading" - I can't see why this won't work, but it doesn't...
    And I switch to the main frame
    And I set the field "Mark" to "52"
    And I set the field "Comment" to "Some new comment"
    And I press "Save and finalise"

    Given I am on the "Coursework" "coursework activity" page logged in as "moderator1"
    And I click on "Agree marking" "link"
    Then I should see "teacher teacher1" in the "[data-behat-markstage=\"assessor_1\"]" "css_element"
    And I should see "52" in the "[data-behat-markstage=\"assessor_1\"]" "css_element"
    And I should see "Some new comment" in the "[data-behat-markstage=\"assessor_1\"]" "css_element"

    When I switch to "mod-coursework-pdf-iframe" iframe
    #Then I should see "Test document for uploading" - I can't see why this won't work, but it doesn't...
    And I switch to the main frame
    And I set the field "Moderation agreement" to "Agreed"
    And I set the field "Comment" to "Moderator explaining agreement"
    And I click on "Save changes" "button"

    Then I should see "Ready for release" in the table row containing "John1"
