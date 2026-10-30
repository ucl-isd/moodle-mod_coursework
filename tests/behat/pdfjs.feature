@mod @mod_coursework @mod_coursework_moderation_view
Feature: Annotate files using pdfjs

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity                   | coursework |
      | course                     | C1         |
      | name                       | Coursework |
      | numberofmarkers            | 2          |
      | moderationagreementenabled | 1          |
      | maxfiles                   | 2          |
      | allowearlyfinalisation     | 1          |
    And the following "users" exist:
      | username   | firstname | lastname | email                  |
      | moderator1 | moderator | 1        | moderator1@example.com |
      | teacher1   | teacher   | teacher1 | teacher1@example.com   |
      | teacher2   | teacher   | teacher2 | teacher2@example.com   |
      | student1   | John1     | student1 | student1@example.com   |
    And the following "role" exists:
      | shortname                      | moderator |
      | name                           | moderator |
      | context_course                 | 1         |
      | mod/coursework:moderate        | allow     |
      | mod/coursework:view            | allow     |
      | mod/coursework:addagreedgrade  | allow     |
      | mod/coursework:editagreedgrade | allow     |
      | mod/coursework:publish         | allow     |
      | local/pdfjs:annotatepdf        | allow     |
      | local/pdfjs:viewannotations    | allow     |
    And the following "course enrolments" exist:
      | user       | course | role      |
      | moderator1 | C1     | moderator |
      | student1   | C1     | student   |
      | teacher1   | C1     | teacher   |
      | teacher2   | C1     | teacher   |
    And the following config values are set as admin:
      | config                 | value |
      | coursework_enablepdfjs | 1     |

  @javascript @_file_upload
  Scenario: The moderator agrees one marker's annotations and the learner sees them
    Given I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/test_pdf.pdf" file to "Upload a file" filemanager
    And I upload "mod/coursework/tests/files_for_uploading/test_pdf2.pdf" file to "Upload a file" filemanager
    And I press "Submit and finalise"

    When I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on "Add mark" "link" in the "student1" "table_row"
    And I set the field "Mark" to "60"
    And I switch to the PDF viewer for "test_pdf.pdf" in the "#feedbackannotator_original" "css_element"
    And I add the text annotation "Marker one note on file one" to the PDF
    And I switch to the main frame
    And I switch to the PDF viewer for "test_pdf2.pdf" in the "#feedbackannotator_original" "css_element"
    And I add the text annotation "Marker one note on file two" to the PDF
    And I switch to the main frame
    And I press "Save and finalise"
    And I should see "Feedback saved" in the "student1" "table_row"

    And I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    And I click on "Add mark" "link" in the "student1" "table_row"
    And I set the field "Mark" to "70"
    And I switch to the PDF viewer for "test_pdf.pdf" in the "#feedbackannotator_original" "css_element"
    And I add the text annotation "Marker two note on file one" to the PDF
    And I switch to the main frame
    And I switch to the PDF viewer for "test_pdf2.pdf" in the "#feedbackannotator_original" "css_element"
    And I add the text annotation "Marker two note on file two" to the PDF
    And I switch to the main frame
    And I press "Save and finalise"
    And I should see "Feedback saved" in the "student1" "table_row"

    And I am on the "Coursework" "coursework activity" page logged in as "moderator1"
    And I click on "Agree marking" "link" in the "student1" "table_row"
    And I set the field "Mark" to "65"
    And I click on "Marker 1" "link" in the "#mod-coursework-pdf-container" "css_element"
    And I click on "Copy all annotations to submission" "button" in the "#mod-coursework-pdf-container" "css_element"
    And I switch to the PDF viewer for "test_pdf.pdf" in the "#feedbackannotator_original" "css_element"
    And "Marker one note on file one" "text" should exist in the ".page[data-page-number='1']" "css_element"
    And I switch to the main frame
    And I switch to the PDF viewer for "test_pdf2.pdf" in the "#feedbackannotator_original" "css_element"
    And "Marker one note on file two" "text" should exist in the ".page[data-page-number='1']" "css_element"
    And I switch to the main frame
    And I press "Save and finalise"
    And I should see "Feedback saved" in the "student1" "table_row"
    And I follow "Release the marks"
    And I press "Confirm"

    Then I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I click on "View annotations on test_pdf.pdf" "link"
    And I switch to a second window
    And "Marker one note on file one" "text" should exist in the ".page[data-page-number='1'] .annotationLayer" "css_element"
    And "Marker two note on file one" "text" should not exist in the ".page[data-page-number='1'] .annotationLayer" "css_element"
    And I close all opened windows
    And I click on "View annotations on test_pdf2.pdf" "link"
    And I switch to a second window
    And "Marker one note on file two" "text" should exist in the ".page[data-page-number='1'] .annotationLayer" "css_element"
    And "Marker two note on file two" "text" should not exist in the ".page[data-page-number='1'] .annotationLayer" "css_element"
