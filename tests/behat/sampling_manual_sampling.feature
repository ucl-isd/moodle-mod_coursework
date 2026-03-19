@mod @mod_coursework @mod_coursework_sampling
Feature: Manual sampling

  As a teacher
  I can manually select the submissions to be included in the sample for a single feedback stage
  So I can select correct sample of students for double marking

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the manager has a capability to allocate students in samplings
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
    And the coursework allocation option is disabled
    And the coursework has sampling enabled
    And the coursework is set to double marker
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | student1 | student   | student1 | student1@example.com |
    And the teacher has a capability to mark submissions
    And there is another teacher
    And the student has a submission
    And there is feedback for the submission from the other teacher
    And the submission deadline has passed
    And the submission is finalised

  @javascript
  Scenario: Manual sampling should not include student when not selected
    And I am on the "Coursework" "coursework activity" page logged in as "manager1"
    When I navigate to "Allocate markers" in current page administration
    And I set the following fields in the "student student2" "table_row" to these values:
      | Included in sample | false |
    And I log out
    And I log in as the teacher
    And I am on the "Coursework" "coursework activity" page
    And I wait "1" seconds
    # I should *NOT* be able to grade the user
    And I should not see "Add feedback"
    Then I should not be able to add the second grade for this student

  @javascript
  Scenario: Single grade should go to the gradebook column when only first stage is in sample
    And I am on the "Coursework" "coursework activity" page logged in as "manager1"
    When I navigate to "Allocate markers" in current page administration
    And I set the following fields in the "student student2" "table_row" to these values:
      | Included in sample | false |
    And I log out
    And I log in as the teacher
    And I am on the "Coursework" "coursework activity" page
    Then I should see the grade given by the initial teacher in the provisional grade column

  @javascript
  Scenario: Manual sampling should include student when selected
    And I am on the "Coursework" "coursework activity" page logged in as "manager1"
    When I navigate to "Allocate markers" in current page administration
    And I set the following fields in the "student student2" "table_row" to these values:
      | Included in sample | true |
    And I log out
    And I log in as the teacher
    And I am on the "Coursework" "coursework activity" page
    # I should be able to grade the user
    And I wait "1" seconds
    And I should see "Add mark"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I set the field "Mark" to "67"
    And I press "Save and finalise"
