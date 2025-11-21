@mod @mod_coursework

Feature: When a coursework uses single marking the marking summary table should display the expected values

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "numberofmarkers" setting is "1" in the database
    And there is a student
    And there is a teacher

  Scenario: Teacher's view when there are no submissions
    Given I log in as the teacher
    And I visit the coursework page
    Then I should see marking summary:
      | Submissions         | 0/1 |
      | Ready for release | 0   |
      | Released    | 0   |

  Scenario: Teacher's view when student has uploaded submission
    Given the student has a submission
    And the submission is finalised
    And I log in as the teacher
    And I visit the coursework page
    Then I should see marking summary:
      | Submissions         | 1/1 |
      | Ready for release | 0   |
      | Released    | 0   |

  Scenario: Teacher's view when submission is marked
    Given the student has a submission
    And the submission is finalised
    And there is finalised feedback for the submission from the teacher
    And I log in as the teacher
    And I visit the coursework page
    Then I should see marking summary:
      | Submissions         | 1/1 |
      | Ready for release | 1   |
      | Released    | 0   |

  @javascript
  Scenario: Manager's view when marks are released
    Given the student has a submission
    And the submission is finalised
    And there is finalised feedback for the submission from the teacher
    And I log in as the manager
    And I visit the coursework page
    And I press the release marks button
    Then I should see marking summary:
      | Submissions         | 1/1 |
      | Ready for release | 0   |
      | Released    | 1   |
