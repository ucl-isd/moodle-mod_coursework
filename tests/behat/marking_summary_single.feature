@mod @mod_coursework

Feature: When a coursework uses single marking the marking summary table should display the expected values

  Background:
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
      | numberofmarkers   | 1          |
    And there is a student
    And there is a teacher

  Scenario: Teacher's view when there are no submissions
    Given I log in as the teacher
    And I am on the "Coursework" "coursework activity" page
    Then I should see marking summary:
      | Submissions         | 0/1 |
      | Ready for release   | 0   |
      | Released            | 0   |

  Scenario: Teacher's view when student has uploaded submission
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And I log in as the teacher
    And I am on the "Coursework" "coursework activity" page
    Then I should see marking summary:
      | Submissions         | 1/1 |
      | Ready for release   | 0   |
      | Released            | 0   |

  Scenario: Teacher's view when submission is marked
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And there is finalised feedback for the submission from the teacher
    And I log in as the teacher
    And I am on the "Coursework" "coursework activity" page
    Then I should see marking summary:
      | Submissions         | 1/1 |
      | Ready for release   | 1   |
      | Released            | 0   |

  @javascript
  Scenario: Manager's view when marks are released
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And there is finalised feedback for the submission from the teacher
    And I log in as the manager
    And I am on the "Coursework" "coursework activity" page
    And I press the release marks button
    Then I should see marking summary:
      | Submissions         | 1/1 |
      | Ready for release   | 0   |
      | Released            | 1   |
