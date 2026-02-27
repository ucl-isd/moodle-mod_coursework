@mod @mod_coursework

Feature: When a coursework uses double marking the marking summary table should display the expected values

  Background:
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
      | numberofmarkers            | 2          |
    And there is a student
    And there is a teacher
    And there is a manager
    And there is another teacher

  Scenario: Teacher's view when there are no submissions
    Given I log in as the teacher
    And I am on the "Coursework" "coursework activity" page
    Then I should see marking summary:
      | Submissions       | 0/1 |
      | Ready for release | 0   |
      | Released          | 0   |

  Scenario: Manager's view when there are no submissions
    Given I log in as the manager
    And I am on the "Coursework" "coursework activity" page
    Then I should see marking summary:
      | Submissions         | 0/1 |
      | Ready for release   | 0   |
      | Released            | 0   |

  Scenario: Teacher's view when there is a submission
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And I log in as the teacher
    And I am on the "Coursework" "coursework activity" page
    Then I should see marking summary:
      | Submissions         | 1/1 |
      | Ready for release   | 0   |
      | Released            | 0   |

  Scenario: Manager's view when there is a submission
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And I log in as the manager
    And I am on the "Coursework" "coursework activity" page
    Then I should see marking summary:
      | Submissions         | 1/1 |
      | Ready for release   | 0   |
      | Released            | 0   |

  Scenario: Teacher's view when submission is marked once
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And there is feedback for the submission from the teacher
    And I log in as the teacher
    And I am on the "Coursework" "coursework activity" page
    Then I should see marking summary:
      | Submissions         | 1/1 |
      | Ready for release   | 0   |
      | Released            | 0   |

  Scenario: Manager's view when submission is marked once
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And there is feedback for the submission from the teacher
    And I log in as the manager
    And I am on the "Coursework" "coursework activity" page
    Then I should see marking summary:
      | Submissions         | 1/1 |
      | Ready for release   | 0   |
      | Released            | 0   |

  Scenario: Manager's view when submission is marked twice
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And there are feedbacks from both teachers
    And I log in as the manager
    And I am on the "Coursework" "coursework activity" page
    Then I should see marking summary:
      | Submissions           | 1/1 |
      | Ready for release     | 0   |
      | Ready for agreement   | 1   |
      | Released              | 0   |

  Scenario: Manager's view when submission has final mark
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And there are feedbacks from both teachers
    And there is final feedback
    And I log in as the manager
    And I am on the "Coursework" "coursework activity" page
    Then I should see marking summary:
      | Submissions           | 1/1 |
      | Ready for release     | 1   |
      | Released              | 0   |
