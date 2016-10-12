# enrol_grabber : an enrolment method that grab enrollees from an other enrolment method

Enrolments grabber enrol method is an enrolment method that grab enrollees from an other enrolment method

## Features
* enrol method
* linked to an other enrol instance of concerned course
* move enrolments from associated enrol method instance to grabbed instance
* possibility to manually unrol/unenrol users from this instance
* while deleting the grabbed instance original method can retrieve original enrolments possibility
  * except if unenrolled from instance

## example usage 
We use this method associated to a exented cohort enrol method limited in time. Once time limited reached enrolments are grabbed by an newly associated Enrolments grabbed instance.
* Enrollees are then transfered from cohort time limited enrolment instance to Enrolments grabbed instance.
* Enrolments are then no more synchronized along cohort changes
* Teacher is free to enrol/unenrol users manually even if his course has a manual enrol instance.

    
## Download

## Installation

### enrol plugin installation
Install in enrol directory (enrol/grabber)

### enrol plugin setting
Under Plugins -> Enrolments -> Enrolments grabber
* Add instance to new courses possibilility
* Enabling or not enrol grabber by defaults
* select default role for manual enrolments part
* While deleting this instance, restore all enrollments of this instance to the associated enrol method default setting

### instance settings
* custom instance name
* Enable
* Default role for manual enrolments part
* enrol instance to grad in the same course (required)
* While deleting this instance, restore all enrollments of this instance to the associated enrol method choice

## Contributions

Contributions of any form are welcome. Github pull requests are preferred.

File any bugs, improvements, or feature requiests in our [issue tracker][issues].

## License
* http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
[my_external_private_files_github]: 
[issues]: 
