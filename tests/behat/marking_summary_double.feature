@mod @mod_coursework

Feature: When a coursework uses double marking the marking summary table should display the expected values

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "numberofmarkers" setting is "2" in the database
    And there is a student
    And there is a teacher
    And there is a manager
    And there is another teacher

  Scenario: Teacher's view when there are no submissions
    Given I log in as the teacher
    And I visit the coursework page
    Then I should see marking summary:
      | Submitted           | 0/1 |
      | Needs marking       | 0   |
      | Marked              | 0   |
      | Marked and released | 0   |

  Scenario: Manager's view when there are no submissions
    Given I log in as the manager
    And I visit the coursework page
    Then I should see marking summary:
      | Submitted            | 0/1 |
      | Needs marking        | 0   |
      | Marked (agreed mark) | 0   |
      | Initial assessor 1   | 0   |
      | Initial assessor 2   | 0   |
      | Marked and released  | 0   |

  Scenario: Teacher's view when there is a submission
    Given the student has a submission
    And the submission is finalised
    And I log in as the teacher
    And I visit the coursework page
    Then I should see marking summary:
      | Submitted           | 1/1 |
      | Needs marking       | 1   |
      | Marked              | 0   |
      | Marked and released | 0   |

  Scenario: Manager's view when there is a submission
    Given the student has a submission
    And the submission is finalised
    And I log in as the manager
    And I visit the coursework page
    Then I should see marking summary:
      | Submitted            | 1/1 |
      | Needs marking        | 1   |
      | Marked (agreed mark) | 0   |
      | Initial assessor 1   | 0   |
      | Initial assessor 2   | 0   |
      | Marked and released  | 0   |

  Scenario: Teacher's view when submission is marked once
    Given the student has a submission
    And the submission is finalised
    And there is feedback for the submission from the teacher
    And I log in as the teacher
    And I visit the coursework page
    Then I should see marking summary:
      | Submitted           | 1/1 |
      | Needs marking       | 0   |
      | Marked              | 0   |
      | Marked and released | 0   |

  Scenario: Manager's view when submission is marked once
    Given the student has a submission
    And the submission is finalised
    And there is feedback for the submission from the teacher
    And I log in as the manager
    And I visit the coursework page
    Then I should see marking summary:
      | Submitted            | 1/1 |
      | Needs marking        | 1   |
      | Marked (agreed mark) | 0   |
      | Initial assessor 1   | 1   |
      | Initial assessor 2   | 0   |
      | Marked and released  | 0   |

  Scenario: Manager's view when submission is marked twice
    Given the student has a submission
    And the submission is finalised
    And there are feedbacks from both teachers
    And I log in as the manager
    And I visit the coursework page
    Then I should see marking summary:
      | Submitted            | 1/1 |
      | Needs marking        | 0   |
      | Marked (agreed mark) | 0   |
      | Initial assessor 1   | 1   |
      | Initial assessor 2   | 1   |
      | Marked and released  | 0   |

  Scenario: Manager's view when submission has final mark
    Given the student has a submission
    And the submission is finalised
    And there are feedbacks from both teachers
    And there is final feedback
    And I log in as the manager
    And I visit the coursework page
    Then I should see marking summary:
      | Submitted            | 1/1 |
      | Needs marking        | 0   |
      | Marked (agreed mark) | 1   |
      | Initial assessor 1   | 1   |
      | Initial assessor 2   | 1   |
      | Marked and released  | 0   |
