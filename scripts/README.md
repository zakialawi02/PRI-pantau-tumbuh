# PRI Pantau Tumbuh - Python Scripts

This repository contains Python scripts for the PRI Pantau Tumbuh project, utilizing Google Earth Engine for environmental monitoring and analysis.

## Prerequisites

-   Python 3.6 or higher
-   Windows, macOS, or Linux operating system

## Setup Instructions

### 1. Virtual Environment

A Python virtual environment has already been created in the `venv/` directory. This isolates the project dependencies from your system-wide Python installation.

To activate the virtual environment:

**Windows:**

```cmd
venv\Scripts\activate
```

**macOS/Linux:**

```bash
source venv/bin/activate
```

You should see `(venv)` at the beginning of your command prompt, indicating that the virtual environment is active.

To deactivate the virtual environment:

```bash
deactivate
```

### 2. Google Earth Engine Authentication

Before using the Earth Engine API, you need to authenticate with your Google account:

```bash
earthengine authenticate
```

Follow the prompts to complete the authentication process.

## Installed Libraries

The following key libraries are installed in the virtual environment:

-   `earthengine-api`: Google Earth Engine Python API
-   `google-cloud-storage`: Google Cloud Storage client library
-   `requests`: HTTP library for Python

A complete list of installed packages and their versions can be found in [requirements.txt](requirements.txt).

To see all installed packages:

```bash
pip list
```

## Usage Examples

### Running Python Scripts

1. Activate the virtual environment
2. Run any Python script in the project:

```bash
python example_earth_engine.py
```

### Example Script

The [example_earth_engine.py](example_earth_engine.py) script demonstrates:

-   Importing the Earth Engine library
-   Basic structure for Earth Engine authentication
-   Error handling for unauthenticated usage

Note: The script will run without errors but will not access actual Earth Engine data until you complete the authentication process.

## Project Structure

```
scripts/
├── venv/                 # Python virtual environment
├── example_earth_engine.py # Example script using Earth Engine
├── requirements.txt       # Python package dependencies
├── package.json           # Node.js dependencies (for other project parts)
└── README.md             # This file
```

## Troubleshooting

### Common Issues

1. **ModuleNotFoundError**: Make sure the virtual environment is activated before running scripts.

2. **Earth Engine initialization failed**: Ensure you've run `earthengine authenticate` and completed the authentication process.

3. **Permission errors on Windows**: Try running your command prompt as an administrator.

### Recreating the Virtual Environment

If you need to recreate the virtual environment:

```bash
# Remove the existing venv directory
rm -rf venv  # On macOS/Linux
rmdir /s venv  # On Windows

# Create a new virtual environment
python -m venv venv

# Activate it
# Windows: venv\Scripts\activate
# macOS/Linux: source venv/bin/activate

# Install Earth Engine API
pip install earthengine-api
```

## Additional Resources

-   [Google Earth Engine API Documentation](https://developers.google.com/earth-engine)
-   [Earth Engine Python API Reference](https://developers.google.com/earth-engine/tutorials)
-   [Google Earth Engine Python Installation Guide](https://developers.google.com/earth-engine/guides/python_install)

## Installing Dependencies

To install all required packages from [requirements.txt](requirements.txt):

```bash
pip install -r requirements.txt
```

## Contributing

1. Activate the virtual environment
2. Install any additional required packages using `pip install package_name`
3. Add the package to requirements if needed:
    ```bash
    pip freeze > requirements.txt
    ```

## License

This project is part of the PRI Pantau Tumbuh initiative.
