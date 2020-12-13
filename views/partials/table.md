## PHP Date Formats

| `format` character |                                                      Description |               Example returned values |
|--------------------|------------------------------------------------------------------|---------------------------------------|
|              *Day* |                                                                - |                                     - |
|                `d` |                    Day of the month, 2 digits with leading zeros |                          `01` to `31` |
|                `D` |                 A textual representation of a day, three letters |                   `Mon` through `Sun` |
|                `j` |                           Day of the month without leading zeros |                           `1` to `31` |
|                `l` |                         (lowercase 'L') Full the day of the week |           `Sunday` through `Saturday` |
|                `N` |           ISO-8601 numeric representation of the day of the week |   `1` (for Mon) through `7` (for Sun) |
|                `S` |                Ordinal suffix for the day of month, 2 characters | `st`, `nd`, `rd` or `th`.  Use w/ `j` |
|                `w` |                    Numeric representation of the day of the week |   `0` (for Sun) through `6` (for Sat) |
|                `z` |                            The day of the year (starting from 0) |                     `0` through `365` |
|             *Week* |                                                                - |                                     - |
|                `W` |           ISO-8601 week number of year, weeks starting on Monday |      `42` (the 42nd week in the year) |
|            *Month* |                                                                - |                                     - |
|                `F` |                   Full name of a month, such as January or March |          `January` through `December` |
|                `m` |            Numeric representation of a month, with leading zeros |                     `01` through `12` |
|                `M` |         A short textual representation of a month, three letters |                   `Jan` through `Dec` |
|                `n` |         Numeric representation of a month, without leading zeros |                      `1` through `12` |
|                `t` |                                Number of days in the given month |                     `28` through `31` |
|             *Year* |                                                                - |                                     - |
|                `L` |                                         Whether it's a leap year |      `1` if leap year, `0` otherwise. |
|                `Y` |                          Full representation of a year, 4 digits |                      `1999` or `2003` |
|                `y` |                             A two digit representation of a year |                          `99` or `03` |
|             *Time* |                                                                - |                                     - |
|                `a` |                        Lowercase Ante meridiem and Post meridiem |                          `am` or `pm` |
|                `A` |                        Uppercase Ante meridiem and Post meridiem |                          `AM` or `PM` |
|                `B` |                                             Swatch Internet time |                   `000` through `999` |
|                `g` |                  12-hour format of an hour without leading zeros |                      `1` through `12` |
|                `G` |                  24-hour format of an hour without leading zeros |                      `0` through `23` |
|                `h` |                     12-hour format of an hour with leading zeros |                     `01` through `12` |
|                `H` |                     24-hour format of an hour with leading zeros |                     `00` through `23` |
|                `i` |                                       Minutes with leading zeros |                          `00` to `59` |
|                `s` |                                       Seconds with leading zeros |                     `00` through `59` |
|                `v` |                      Milliseconds. Same note applies as for `u`. |                                 `654` |
|         *Timezone* |                                                                - |                                     - |
|                `e` |                                              Timezone identifier |       `UTC`, `GMT`, `Atlantic/Azores` |
|                `I` | (uppercase i) Whether or not the date is in daylight saving time |            `1` if DST, `0` otherwise. |
|                `T` |                                            Timezone abbreviation |                      `EST`, `MDT` ... |
|   *Full Date/Time* |                                                                - |                                     - |
|                `c` |                                                    ISO 8601 date |             2004-02-12T15:19:21+00:00 |
|                `r` |      [RFC 2822](http://www.faqs.org/rfcs/rfc2822) formatted date |     `Thu, 21 Dec 2000 16:01:07 +0200` |
|                `U` |                        Seconds since Unix Epoch (January 1 1970) |                          `1604604788` |