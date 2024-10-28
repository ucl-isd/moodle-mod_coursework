@mod @mod_coursework @mod_coursework_sampling_manual
Feature: Manual sampling

    As a teacher
    I can manually select the submissions to be included in the sample for a single feedback stage
    So I can select correct sample of students for double marking

  Background:
    Given there is a course
    And I am logged in as a manager
    And the manager has a capability to allocate students in samplings
    And there is a coursework
    And the coursework allocation option is disabled
    And the coursework has sampling enabled
    And the coursework is set to double marker
    And there is a student
    And there is a teacher
    And the teacher has a capability to mark submissions
    And there is another teacher
    And the student has a submission
    And there is feedback for the submission from the other teacher
    And the submission deadline has passed
    And the submission is finalised

  @javascript
  Scenario: Manual sampling should include student when selected
    When I visit the allocations page
    And I select a student as a part of the sample for the second stage
    And I save everything
    And I log out
    And I log in as the teacher
    And I visit the coursework page
    # I should be able to grade the user
    And I expand the coursework grading row
    And I wait "1" seconds
    And I should see "New feedback"
    And I click on the only interactable link with title "New feedback"
    And I grade the submission as 67 using the ajax form

  @javascript
  Scenario: Manual sampling should not include student when not selected
    When I visit the allocations page
    And I deselect a student as a part of the sample for the second stage
    And I save everything
    And I log out
    And I log in as the teacher
    And I visit the coursework page
    And I expand the coursework grading row
    And I wait "1" seconds
    # I should *NOT* be able to grade the user
    And I should not see "New feedback"
    Then I should not be able to add the second grade for this student

  @javascript
  Scenario: Single grade should go to the gradebook column when only first stage is in sample
    When I visit the allocations page
    And I deselect a student as a part of the sample for the second stage
    And I save everything
    And I log out
    And I log in as the teacher
    And I visit the coursework page
    Then I should see the grade given by the initial teacher in the provisional grade column
