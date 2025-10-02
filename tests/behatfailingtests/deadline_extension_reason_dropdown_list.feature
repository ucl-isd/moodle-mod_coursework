@mod @mod_coursework @RVC_PT_83738596 @mod_coursework_deadline_exten_reason
Feature: Deadline extension reasons dropdown list

  As an OCM admin
  I can create deadline extension reasons in a text box,
  so that the specific reason can be selected for the new cut off date.

  Background:
    Given there is a course
    And there is a coursework
    And there is a student
    And the coursework individual extension option is enabled

  @javascript
  Scenario: The teacher can add a reason for the deadline extension to an individual submission
    Given the coursework deadline has passed
    And there are some extension reasons configured at site level
    And I log in as a manager
    And I visit the coursework page
    And I click on "New extension" "link"
    And I enter an extension "+1 week" in the form with reason code "0"
    And I click on "Save" "button"
    And I wait until the page is ready
    And I wait "1" seconds
    And I should see "Extension saved successfully"
    Then I visit the coursework page
    When I click on the edit extension icon for the student
    And I wait until the page is ready
    And I wait "1" seconds
    Then I should see the extension "+1 week" in the form with reason code "0"

  @javascript
  Scenario: The teacher can edit a deadline extension and its reason to an individual submission
    Given the coursework deadline has passed
    And there are some extension reasons configured at site level
    And there is an extension for the student which has expired
    And I log in as a manager
    And I visit the coursework page
    And I click on "Edit extension" "link"
    And I enter an extension "+4 weeks" in the form with reason code "0"
    And I click on "Save" "button"
    And I wait until the page is ready
    And I wait "1" seconds
    And I should see "Extension saved successfully"
    Then I visit the coursework page
    And I click on the edit extension icon for the student
    And I wait until the page is ready
    And I wait "1" seconds
    Then I should see the extension "+4 weeks" in the form with reason code "0"
