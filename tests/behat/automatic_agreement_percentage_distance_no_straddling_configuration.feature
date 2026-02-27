@mod @mod_coursework @mod_coursework_automatic_agreement_no_straddling
Feature: Configure grade boundaries for automatic agreement of grades

  Scenario: An administrator can control the configuration of the agreed grade class bands - non numeric input and non-contiguous ranges are rejected
    Given I am logged in as "admin"
    And I navigate to "Plugins > Activity modules > Coursework" in site administration
    And I set the field "Average grade (no straddling class boundaries)" to "0|100"
    And I press "Save changes"
    And I should see "At least two grade classes must be defined. Please add more lines to the setting."
    And I set the field "Average grade (no straddling class boundaries)" to multiline:
    """
    50|100
    0|49.99
    """
    And I press "Save changes"
    And I should see "Changes saved"
    And I set the field "Average grade (no straddling class boundaries)" to multiline:
    """
    50A|100
    0|49.99
    """
    And I press "Save changes"
    And I should see "Some settings were not changed due to an error."
    # Example values provided are accepted.
    And I set the field "Average grade (no straddling class boundaries)" to multiline:
    """
    70.00|100.00
    60.00|69.99
    50.00|59.99
    40.00|49.99
    1.00|39.99
    0.00|0.99
    """
    And I press "Save changes"
    And I should see "Changes saved"
    # Non-contiguous ranges rejected
    And I set the field "Average grade (no straddling class boundaries)" to multiline:
    """
    70.00|100.00
    60.00|69.98
    50.00|59.99
    40.00|49.99
    1.00|39.99
    0.00|0.99
    """
    And I press "Save changes"
    And I should see "Some settings were not changed due to an error."
    And I should see "Invalid value on line 2 - the second value on this line (69.98) must be exactly 0.01 lower than the previous line's second value (70) (i.e. value gaps or overlaps between lines are not allowed)"
    And I set the field "Average grade (no straddling class boundaries)" to multiline:
    """
    70.00|100.00
    60.00|70
    50.00|59.99
    40.00|49.99
    1.00|39.99
    0.00|0.99
    """
    And I press "Save changes"
    And I should see "Some settings were not changed due to an error."
    And I should see "Invalid value on line 2 - the second value on this line (70) must be exactly 0.01 lower than the previous line's second value (70) (i.e. value gaps or overlaps between lines are not allowed)"
    # 3 decimal place values rejected
    And I set the field "Average grade (no straddling class boundaries)" to multiline:
    """
    70.00|100.00
    60.00|69.999
    50.00|59.99
    40.00|49.99
    1.00|39.99
    0.00|0.99
    """
    And I press "Save changes"
    And I should see "Some settings were not changed due to an error."
    And I should see "Invalid value '69.999' on line 2 - each line must have two whole numbers or decimals (with a maximum of two decimal places), separated by a | character"
