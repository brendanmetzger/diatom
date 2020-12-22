# Data

This directory is holds content, configurations, and any application data that would conceiveably be useful outside of the application itself. As such, it is a good idea **to ignore this whole directory in `.gitignore` and track or protect it outside of the main application repository.**


## Naming

Sections are named after properly classes—include the namespace if declared. The config then applies to that class, given it is using the `configurable` trait, which exposes a public static method of `config`.
