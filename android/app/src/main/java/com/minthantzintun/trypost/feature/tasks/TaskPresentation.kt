package com.minthantzintun.trypost.feature.tasks

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.designsystem.Spacing
import com.minthantzintun.trypost.core.designsystem.StatusPill
import com.minthantzintun.trypost.core.designsystem.Tone
import com.minthantzintun.trypost.core.designsystem.TryPostTheme
import com.minthantzintun.trypost.core.model.TagColor
import com.minthantzintun.trypost.core.model.Task
import com.minthantzintun.trypost.core.model.TaskDue
import com.minthantzintun.trypost.core.model.TaskPriority
import com.minthantzintun.trypost.core.model.TaskStatus
import com.minthantzintun.trypost.core.model.TaskTagRef
import java.time.LocalDate
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.time.format.FormatStyle

/** A Tag group colour's text tone on its own container, for the theme in use. */
@Composable
fun TagColor.tone(): Tone = with(TryPostTheme.tagColors) {
    when (this@tone) {
        TagColor.Gray -> gray
        TagColor.Red -> red
        TagColor.Orange -> orange
        TagColor.Yellow -> yellow
        TagColor.Green -> green
        TagColor.Teal -> teal
        TagColor.Blue -> blue
        TagColor.Brown -> brown
    }
}

@StringRes
fun TagColor.labelRes(): Int = when (this) {
    TagColor.Gray -> R.string.tag_color_gray
    TagColor.Red -> R.string.tag_color_red
    TagColor.Orange -> R.string.tag_color_orange
    TagColor.Yellow -> R.string.tag_color_yellow
    TagColor.Green -> R.string.tag_color_green
    TagColor.Teal -> R.string.tag_color_teal
    TagColor.Blue -> R.string.tag_color_blue
    TagColor.Brown -> R.string.tag_color_brown
}

/** A Task tag as a small pill in its Tag group's colour, read by TalkBack with its group. */
@Composable
fun TaskTagPill(taskTag: TaskTagRef, modifier: Modifier = Modifier) {
    val tone = taskTag.color.tone()
    val description = stringResource(R.string.tasks_tag_in_group, taskTag.name, taskTag.tagGroupName)

    Surface(
        shape = CircleShape,
        color = tone.container,
        contentColor = tone.content,
        modifier = modifier.semantics { contentDescription = description },
    ) {
        Text(
            text = taskTag.name,
            style = MaterialTheme.typography.labelMedium,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.padding(horizontal = Spacing.sm, vertical = Spacing.xxs),
        )
    }
}

/** A Tag group's colour as a dot. */
@Composable
fun TagColorDot(color: TagColor, modifier: Modifier = Modifier, size: Dp = 12.dp) {
    Box(modifier = modifier.size(size).background(color.tone().content, CircleShape))
}

@Composable
fun TaskStatus.label(): String = stringResource(
    when (this) {
        TaskStatus.Todo -> R.string.task_status_todo
        TaskStatus.InProgress -> R.string.task_status_in_progress
        TaskStatus.Done -> R.string.task_status_done
    },
)

@DrawableRes
fun TaskStatus.icon(): Int = when (this) {
    TaskStatus.Todo -> R.drawable.ic_circle
    TaskStatus.InProgress -> R.drawable.ic_progress
    TaskStatus.Done -> R.drawable.ic_circle_check
}

@Composable
fun TaskStatus.tone(): Tone = with(TryPostTheme.statusColors) {
    when (this@tone) {
        TaskStatus.Todo -> neutral
        TaskStatus.InProgress -> scheduled
        TaskStatus.Done -> published
    }
}

@Composable
fun TaskStatusPill(status: TaskStatus, modifier: Modifier = Modifier) {
    StatusPill(label = status.label(), icon = status.icon(), tone = status.tone(), modifier = modifier)
}

@StringRes
fun TaskPriority.labelRes(): Int = when (this) {
    TaskPriority.None -> R.string.task_priority_none
    TaskPriority.Low -> R.string.task_priority_low
    TaskPriority.Medium -> R.string.task_priority_medium
    TaskPriority.High -> R.string.task_priority_high
}

@Composable
fun TaskPriority.tone(): Tone = with(TryPostTheme.statusColors) {
    when (this@tone) {
        TaskPriority.None, TaskPriority.Low -> neutral
        TaskPriority.Medium -> publishing
        TaskPriority.High -> failed
    }
}

/** The priority as a flag pill; nothing for None. */
@Composable
fun TaskPriorityPill(priority: TaskPriority, modifier: Modifier = Modifier) {
    if (priority == TaskPriority.None) {
        return
    }

    StatusPill(
        label = stringResource(R.string.task_priority_pill, stringResource(priority.labelRes())),
        icon = R.drawable.ic_flag,
        tone = priority.tone(),
        modifier = modifier,
    )
}

private val ShortTime: DateTimeFormatter = DateTimeFormatter.ofLocalizedTime(FormatStyle.SHORT)

/** A day: "Mon, 3 Feb" this year, the medium date otherwise; Today, Tomorrow and Yesterday by name. */
@Composable
fun dayLabel(day: LocalDate, today: LocalDate): String = when (day) {
    today -> stringResource(R.string.posts_today)
    today.plusDays(1) -> stringResource(R.string.posts_tomorrow)
    today.minusDays(1) -> stringResource(R.string.posts_yesterday)
    else -> day.format(
        if (day.year == today.year) DateTimeFormatter.ofPattern("EEE, d MMM") else DateTimeFormatter.ofLocalizedDate(FormatStyle.MEDIUM),
    )
}

/** The due as one line: "Tomorrow", or "Tomorrow, 15:00". */
@Composable
fun dueLabel(due: TaskDue, today: LocalDate): String = when (due) {
    is TaskDue.AllDay -> dayLabel(due.date, today)
    is TaskDue.Timed -> stringResource(R.string.tasks_due_day_time, dayLabel(due.date, today), due.at.toLocalTime().format(ShortTime))
}

/** The due inside its day's section: the time, or "All day". */
@Composable
fun dueTimeLabel(task: Task, zone: ZoneId): String? = when (val due = task.due(zone)) {
    null -> null
    is TaskDue.AllDay -> stringResource(R.string.tasks_all_day)
    is TaskDue.Timed -> due.at.toLocalTime().format(ShortTime)
}
