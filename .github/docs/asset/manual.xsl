<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

    <xsl:output method="html" indent="yes"/>

    <xsl:template match="/">
        <html>
            <head>
                <title>HydePHP CLI Command List</title>
                <style>
                    body {
                    font-family: Arial, sans-serif;
                    }
                    .command {
                    border: 1px solid #ccc;
                    margin: 10px 0;
                    padding: 10px;
                    border-radius: 5px;
                    }
                    .command h2 {
                    margin: 0;
                    font-size: 1.2em;
                    color: #333;
                    }
                    .command .description, .command .help {
                    margin: 5px 0;
                    color: #666;
                    }
                    .option {
                    margin-left: 20px;
                    }
                    .option h3 {
                    margin: 0;
                    font-size: 1em;
                    color: #555;
                    }
                    .option p {
                    margin: 0;
                    color: #777;
                    }
                </style>
            </head>
            <body>
                <h1>HydePHP CLI Command List</h1>
                <div>
                    <xsl:apply-templates select="symfony/commands/command"/>
                </div>
            </body>
        </html>
    </xsl:template>

    <xsl:template match="command">
        <div class="command">
            <h2>
                <xsl:value-of select="@name"/>
            </h2>
            <div class="description">
                <strong>Description:</strong>
                <xsl:value-of select="description"/>
            </div>
            <div class="help">
                <strong>Help:</strong>
                <xsl:value-of select="help"/>
            </div>
            <div class="usages">
                <strong>Usages:</strong>
                <ul>
                    <xsl:for-each select="usages/usage">
                        <li><xsl:value-of select="."/></li>
                    </xsl:for-each>
                </ul>
            </div>
            <div class="options">
                <strong>Options:</strong>
                <ul>
                    <xsl:for-each select="options/option">
                        <li class="option">
                            <h3>
                                <xsl:value-of select="@name"/>
                                <xsl:if test="@shortcut">
                                    (<xsl:value-of select="@shortcut"/>)
                                </xsl:if>
                            </h3>
                            <p><strong>Description:</strong> <xsl:value-of select="description"/></p>
                        </li>
                    </xsl:for-each>
                </ul>
            </div>
        </div>
    </xsl:template>

</xsl:stylesheet>
