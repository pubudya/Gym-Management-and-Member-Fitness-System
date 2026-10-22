@echo off
setlocal
cd /d "%~dp0"
if not exist lib mkdir lib
if not exist out mkdir out
echo Place mysql-connector-j-8.4.0.jar inside java\lib then run this file.
if not exist lib\mysql-connector-j-8.4.0.jar (
  echo Missing lib\mysql-connector-j-8.4.0.jar
  exit /b 1
)
javac -cp "lib\mysql-connector-j-8.4.0.jar" -d out src\com\gymmanagement\AutomationEngine.java
if errorlevel 1 exit /b 1
java -cp "out;lib\mysql-connector-j-8.4.0.jar" com.gymmanagement.AutomationEngine
