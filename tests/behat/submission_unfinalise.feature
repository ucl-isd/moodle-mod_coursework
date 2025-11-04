@mod @mod_coursework @mod_coursework_submission_unfinalise @javascript
Feature: Auto finalising before cron runs

  As a manager
  I want to be able to unfinalise a student's submission after the deadline has passed
  if the coursework allows late submissions
  to enable the student to resubmit a different document

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "allowlatesubmissions" setting is "1" in the database
    And there is a student
    And there is a teacher
    And there is another teacher
    And the student has a submission
    And the submission deadline has passed

  Scenario: Student visits the coursework page and sees the submission is finalised and cannot edit
    Given I log in as a student
    And I visit the coursework page
    And I should see "Submitted"
    # So far the submission has not been unfinalised so I cannot edit as student.
    And I should not see "Edit your submission"

  Scenario: Teacher *cannot unfinalise* when visits the coursework page and sees the submission is finalised when the deadline has passed.
    When I am logged in as a teacher
    And I visit the coursework page
    # I am not able to see the actions button so cannot click "Unfinalise submission".
    And I should not see "Actions" in the table row containing "student student1"
    And I should not see "Unfinalise submission"

  Scenario: Manager *can unfinalise* when visits the page and sees the submission is finalised when the deadline has passed.
    When I am logged in as a manager
    And I visit the coursework page
    # The submission is finalised at this point so the submission will not say "Draft".
    And I should not see "Draft" in the table row containing "student student1"
    And I click on "Actions" "button"
    And I click on "Unfinalise submission" "link"
    And I should see "Are you sure you want to unfinalise the submission for student student1?"
    And I click on "Yes" "button"
    And I wait until the page is ready
    # The submission is now unfinalised so will say "Draft".
    And I should see "Draft" in the table row containing "student student1"
    And I log out


    And I log in as a student
    And I visit the coursework page
    And I should see "Submitted"
    # Now the submission has been unfinalised so I can edit as student.
    And I should see "Edit your submission"
    And I click on "Edit your submission" "link"
    And I click on "Submit" "button"
    # Now it is finalised again, so I can no longer edit it.
    And I should see "Submitted"
    And I should not see "Edit your submission"
    And I log out

    When I am logged in as a manager
    And I visit the coursework page
    # The submission is now finalised again (by the student) so will not say "Draft".
    And I should not see "Draft" in the table row containing "student student1"

  Scenario: Manager *cannot unfinalise* when visits the page and sees the submission is finalised when the deadline has passed and activity does not allow late submissions.
    When I am logged in as a manager
    And the coursework "allowlatesubmissions" setting is "0" in the database
    And I visit the coursework page
    # The submission is finalised at this point (so the submission will not say "Draft").
    And I should not see "Draft" in the table row containing "student student1"
    # Because late submissions are not allowed, I cannot unfinalise.
    And I should not see "Actions" in the table row containing "student student1"
    And I log out

