<?php

// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http:www.gnu.org/licenses/>.

/**
 * Kaltura video assignment grade preferences form
 *
 * @package    Repository
 * @subpackage Kaltura
 * @license    http:www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
$schema = '<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <xsd:element name="metadata">
    <xsd:complexType>
      <xsd:sequence>
        <xsd:element id="md_3A522FB0-2E47-1CA7-C26F-293C06079B5B" name="SystemShare" minOccurs="0" maxOccurs="1" type="textType">
          <xsd:annotation>
            <xsd:documentation>System Share</xsd:documentation>
            <xsd:appinfo>
              <label>System Share</label>
              <key>System Share</key>
              <searchable>true</searchable>
              <timeControl>false</timeControl>
              <description>System Share</description>
            </xsd:appinfo>
          </xsd:annotation>
        </xsd:element>
        <xsd:element id="md_B1BFF373-6791-4BEF-5709-293C4A1EFBBD" name="CourseShare" minOccurs="0" maxOccurs="unbounded" type="textType">
          <xsd:annotation>
            <xsd:documentation>Course Share</xsd:documentation>
            <xsd:appinfo>
              <label>Course Share</label>
              <key>Course Share</key>
              <searchable>true</searchable>
              <timeControl>false</timeControl>
              <description>Course Share</description>
            </xsd:appinfo>
          </xsd:annotation>
        </xsd:element>
      </xsd:sequence>
    </xsd:complexType>
  </xsd:element>
  <xsd:complexType name="textType">
    <xsd:simpleContent>
      <xsd:extension base="xsd:string"/>
    </xsd:simpleContent>
  </xsd:complexType>
  <xsd:complexType name="dateType">
    <xsd:simpleContent>
      <xsd:extension base="xsd:long"/>
    </xsd:simpleContent>
  </xsd:complexType>
  <xsd:complexType name="objectType">
    <xsd:simpleContent>
      <xsd:extension base="xsd:string"/>
    </xsd:simpleContent>
  </xsd:complexType>
  <xsd:simpleType name="listType">
    <xsd:restriction base="xsd:string"/>
  </xsd:simpleType>
</xsd:schema>';
