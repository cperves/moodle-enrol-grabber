@enrol @enrol_grabber
Feature: enrol grabber with no instance attached

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | user1 | First | User | first@example.com |
      | user2 | Second | User | second@example.com |
    And the following course exists:
      | name      | Test course |
      | shortname | C1          |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | user1 | C1     | student |
      | user2 | C1     | student |
    And I log in as "admin"
    And I navigate to "Plugins > Enrolments > Manage enrol plugins" in site administration
    And I click on "Enable" "link" in the "Enrolments grabber" "table_row"
    And I add "Enrolments grabber" enrolment method in "C1" with:
      | enrol instance to grab (same course) | -1      |
    And I am on course index

  @javascript
  Scenario: Tests without time limit and with it
    Given I log in as "admin"
    Then I am on the "C1" "enrolment methods" page
    And I should see "Enrolments grabber (Not attached grabber instance)"
    And I should see "0" in the "Enrolments grabber (Not attached grabber instance)" "table_row"
